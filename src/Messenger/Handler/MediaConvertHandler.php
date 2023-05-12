<?php
namespace Coa\VideolibraryBundle\Messenger\Handler;

use Coa\MessengerBundle\Messenger\Handler\Handler;
use Coa\VideolibraryBundle\Entity\Video;
use Coa\VideolibraryBundle\Event\TranscodingEvent;
use Coa\VideolibraryBundle\Service\MediaConvertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Traitement de tout les messages en rapport avec le microservice MediaConvert
 * il surveille les action comme :
 *  - la progression du transcodage.
 *  - l'etat d'un transcodage
 *  - la modification d'un poster video
 *  - la suppression d'un poster video
 *  - l'ajout d'un poster video
 *  - mise a jour de la durée d'une video
 *  - suppression d'une video
 *
 * @author Zacharie Assagou <zacharie.assagou@coteouest.ci>
 */
class MediaConvertHandler extends Handler {

    private ContainerBagInterface $container;
    private EntityManagerInterface $em;
    private MediaConvertService $mediaConvert;
    private EventDispatcherInterface $dispatcher;

    /**
     * @param ContainerBagInterface $container
     */
    public function __construct(ContainerBagInterface $container,
                                EntityManagerInterface $em, MediaConvertService $mediaConvert, EventDispatcherInterface $dispatcher){
        parent::__construct("mc\..+",1);
        $this->container = $container;
        $this->em = $em;
        $this->mediaConvert = $mediaConvert;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param array $payload
     * @return mixed
     */
    protected function run(string $bindingKey,array $payload){
        dump("---------------------------> on rentre dans MediaConvertProgressHandler <---------------------------------");
        $video_entity = $this->container->get("coa_videolibrary.video_entity");
        $rep = $this->em->getRepository($video_entity);

        $code = $payload["code"];
        $jobId = $payload["jobId"];
        $fileSize = $payload["fileSize"];
        $bucket = $payload["bucket"];
        $originalFilename = $payload["originalFilename"];
        $region = $payload["region"];

        /** @var Video $video */
        if(!($video = $rep->findOneBy(["code"=>$payload["code"]]))) {
            $video = new $video_entity();
            $video->setCode($code);
            $video->setOriginalFilename($originalFilename);
            $video->setFileSize($fileSize);
            $video->setState("SUBMITTED");
            $video->setJobRef($jobId);
            $video->setIsTranscoded(false);
            $video->setPoster(null);
            $video->setScreenshots(null);
            $video->setWebvtt(null);
            $video->setManifest(null);
            $video->setDuration(null);
            $video->setCreatedAt(new \DateTimeImmutable());
            $video->setAuthor(null);
            $video->setEncrypted(true);
            $video->setUseFor("episode");
            $this->em->persist($video);
            $video->setBucket($bucket);
            $video->setRegion($region);
            $video->setJobPercent(0);
            $this->em->persist($video);
            $this->em->flush();
        }

        switch ($bindingKey){
            // video soumise en transcodage avec disponibilité
            // il faut creer l'entité video
            case "mc.transcoding.submitted":

                break;

            case "mc.transcoding.error": // status d'un transcodage
                $video->setState("ERROR");
                $this->em->persist($video);
                $this->em->flush();
                break;

            case "mc.transcoding.progressing": // status d'un transcodage
                $video->setState("PROGRESSING");
                if(isset($payload["jobPercent"])){
                    $video->setJobPercent($payload["jobPercent"]);
                }
                $this->em->persist($video);
                $this->em->flush();
                break;

            case "mc.transcoding.complete": // status d'un transcodage

                $video->setIsTranscoded(true);
                $video->setJobPercent(100);

                $r = $this->mediaConvert->getJob($video->getJobRef());
                if(!$r["status"]) break;

                $job = @$r["data"];
                $video->setState($job["status"]);
                $video->setDuration($job["duration"]);

                if (isset($job["startTime"]) && $job["startTime"]) {
                    $video->setjobStartTime(new \DateTimeImmutable($job["startTime"]));
                }

                if (isset($job["submitTime"]) && $job["submitTime"]) {
                    $video->setjobSubmitTime(new \DateTimeImmutable($job["submitTime"]));
                }

                if (isset($job["finishTime"]) && $job["finishTime"]) {
                    $video->setjobFinishTime(new \DateTimeImmutable($job["finishTime"]));
                }

                $bucket = $video->getBucket(); //@$job["bucket"];
                $prefix = $video->getCode()."/"; //@$job["prefix"];
                $job["resources"] = $this->mediaConvert->getResources($bucket,$prefix);

                if (isset($job["resources"]) && count($job["resources"])) {
                    #fix bug #045 not enough images on getstatus
                    $video->setDownload(@$job["resources"]["download"][0]);
                    $video->setPoster($job["resources"]["thumnails"][0]);
                    $video->setScreenshots($job["resources"]["thumnails"]);
                    # add random poster selecttion
                    if(count(@$job["resources"]["thumnails"]) > 1){
                        $index = random_int(1,count($job["resources"]["thumnails"])-1);
                        $video->setPoster($job["resources"]["thumnails"][$index]);
                    }
                    $video->setWebvtt($job["resources"]["webvtt"]);
                    $video->setManifest($job["resources"]["manifests"][0]);
                    $video->setVariants(array_slice($job["resources"]["manifests"], 1));
                }
                $this->em->persist($video);
                $this->em->flush();

                if(in_array($job["status"],["COMPLETE","ERROR","CANCELED"])){
                    // new: event "coa_videolibrary.transcoding" is emitted
                    $event = new TranscodingEvent($video);
                    $this->dispatcher->dispatch($event,"coa_videolibrary.transcoding");
                }
                break;
        }
    }
}