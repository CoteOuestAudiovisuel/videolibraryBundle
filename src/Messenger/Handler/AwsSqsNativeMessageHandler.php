<?php
namespace Coa\MessengerBundle\Messenger\Handler;
use App\Entity\Video;
use App\Messenger\Message\AwsSqsNativeMessage;
use Coa\VideolibraryBundle\Event\TranscodingEvent;
use Coa\VideolibraryBundle\Service\MediaConvertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;


/**
 * @author Zacharie Assagou <zacharie.assagou@coteouest.ci>
 */
class AwsSqsNativeMessageHandler implements MessageHandlerInterface{

    private ContainerBagInterface $container;
    private EntityManagerInterface $em;
    private MediaConvertService $mediaConvert;
    private EventDispatcherInterface $dispatcher;

    public function __construct(ContainerBagInterface $container,
                                EntityManagerInterface $em,
                                MediaConvertService $mediaConvert, EventDispatcherInterface $dispatcher){
        $this->container = $container;
        $this->em = $em;
        $this->mediaConvert = $mediaConvert;
        $this->dispatcher = $dispatcher;
    }

    public function retrieveKeyFromS3Uri(string $uri): string {
        $key = explode("/",$uri);
        $key = array_slice($key,3);
        $key = implode("/",$key);
        return $key;
    }

    public function __invoke(AwsSqsNativeMessage $message){
        /*$payload = json_decode($message->getMessage(),true);
        $rep = $this->em->getRepository(Video::class);

        switch ($payload["source"]){
            case "aws.mediaconvert":
                $detail = $payload["detail"];
                $metadata = $detail["userMetadata"];
                if(!isset($metadata["code"]) || !isset($metadata["application"])) return;

                $jobId = $detail["jobId"];
                $code = $metadata["code"];
                $bucket = $metadata["bucket"];
                $fileSize = $metadata["fsize"];
                $originalFilename = $metadata["fname"];
                $region = $metadata["region"];
                $detailStatus = strtoupper($detail["status"]);

                if(!($video = $rep->findOneBy(["code"=>$code]))){
                    $video = new Video();
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
                    $this->em->flush();
                }

                switch($detailStatus){
                    case "PROGRESSING":
                    case "STATUS_UPDATE":
                        if(in_array($video->getState(),["PROGRESSING","SUBMITTED"])) {
                            $video->setState("PROGRESSING");
                            if($detailStatus == "STATUS_UPDATE"){
                                $jobProgress = $detail["jobProgress"];
                                $percent = $jobProgress["jobPercentComplete"];
                                $currentPhase = $jobProgress["currentPhase"];
                                $video->setJobPercent($percent);
                            }
                        }
                        break;

                    case "COMPLETE":
                        $video->setIsTranscoded(true);
                        $r = $this->mediaConvert->getJob($video->getJobRef());
                        if(!$r["status"]) break;

                        $job = @$r["data"];
                        if(isset($job["status"]) && $job["status"] != $video->getState()){
                            $video->setState($job["status"]);
                        }

                        if(isset($job["duration"]) && $job["duration"] != $video->getDuration()){
                            $video->setDuration($job["duration"]);
                        }

                        if($job["status"] == "COMPLETE") {
                            $video->setJobPercent(100);
                        }
                        else{
                            $video->setJobPercent($job["jobPercent"]);
                        }

                        if (isset($job["startTime"]) && $job["startTime"]) {
                            $video->setjobStartTime(new \DateTimeImmutable($job["startTime"]));
                        }

                        if (isset($job["submitTime"]) && $job["submitTime"]) {
                            $video->setjobSubmitTime(new \DateTimeImmutable($job["submitTime"]));
                        }

                        if (isset($job["finishTime"]) && $job["finishTime"]) {
                            $video->setjobFinishTime(new \DateTimeImmutable($job["finishTime"]));
                        }

                        if($job["status"] == "COMPLETE"){
                            $bucket = $video->getBucket(); //@$job["bucket"];
                            $prefix = $video->getCode()."/"; //@$job["prefix"];
                            $job["resources"] = $this->mediaConvert->getResources($bucket,$prefix);
                        }

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

                        if(in_array($job["status"],["COMPLETE","ERROR","CANCELED"])){
                            // new: event "coa_videolibrary.transcoding" is emitted
                            $event = new TranscodingEvent($video);
                            $this->dispatcher->dispatch($event,"coa_videolibrary.transcoding");
                        }
                        break;

                    case "ERROR":
                        $video->setState("ERROR");
                        break;
                }

                $this->em->persist($video);
                $this->em->flush();
                break;
        }*/

        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);

        $date = (new \DateTime())->format("Y-m-d");
        $fs = new Filesystem();
        $folder = $this->container->get('kernel.project_dir')."/applog/broker/log";
        if(!$fs->exists($folder)){
            $fs->mkdir($folder);
        }
        $log_file = $folder."/$date.log";
        $data = $serializer->serialize($message, 'json');
        $fs->appendToFile($log_file, $data."\n");
    }
}