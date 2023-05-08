<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\VideolibraryBundle\Entity\Video;
use Coa\VideolibraryBundle\Event\MultipartUploadEvent;
use Coa\VideolibraryBundle\Extensions\Twig\AwsS3Url;
use Coa\VideolibraryBundle\Service\CoaVideolibraryService;
use Coa\VideolibraryBundle\Service\MediaConvertService;
use Coa\VideolibraryBundle\Service\S3Service;

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Doctrine\ORM\QueryBuilder;


/**
 * @Route("/videolibrary", name="coa_videolibrary_")
 * @IsGranted("ROLE_MANAGER")
 * Class VideolibraryController
 * @package App\Controller
 */
class VideolibraryController extends AbstractController
{

    private function getVideo(string $code){
        $entity_class = $this->getParameter("coa_videolibrary.video_entity");
        $em = $this->getDoctrine()->getManager();
        $rep = $em->getRepository($entity_class);

        if(!($video = $rep->findOneBy(["code"=>$code]))){
            throw $this->createNotFoundException();
        }
        return $video;
    }

    private  function  getTargetDirectory(){
        $basedir = $this->getParameter('kernel.project_dir')."/public/coa_videolibrary_upload";
        if(!file_exists($basedir)){
            mkdir($basedir);
        }
        return $basedir;
    }


    /**
     * @Route("/", name="index")
     */
    public function index(Request $request, EntityManagerInterface $em, CoaVideolibraryService $coaVideolibrary,HttpClientInterface $httpClient): Response
    {
        $data = [];
        $service = $request->query->get("service");
        if($service){
            $data = $coaVideolibrary->searchInConstellation($service);
        }
        else{
            $data = $coaVideolibrary->search();
        }

        $view = '@CoaVideolibrary/home/index.html.twig';

        if($request->isXmlHttpRequest()){
            $acceptHeader = AcceptHeader::fromString($request->headers->get('Accept'));
            $view = '@CoaVideolibrary/home/item-render.html.twig';

            if($request->query->get("__source") == "modal-search"){
                $view = '@CoaVideolibrary/home/modal-video-item.html.twig';
            }
        }

        return $this->render($view, [
            'videos' => $data,
            "service" => $service
        ]);
    }

    /**
     * @Route("/{code}/view", name="show_video")
     * affichage une entité video
     */
    public function showVideo(Request $request, string $code): Response
    {
        $video = $this->getVideo($code);
        return $this->render("", [
            'video' => $video
        ]);
    }

    /**
     * @Route("/{code}/delete", name="delete_video", methods={"POST"})
     * @IsGranted("ROLE_VIDEOLIBRARY_DELETE")
     *
     * supprimer une entité video
     */
    public function deleteVideo(Request $request, S3Service $s3Service, string $code): Response
    {
        $em = $this->getDoctrine()->getManager();
        $video = $this->getVideo($code);
        $result = ["status"=>false];

        $prefix = $video->getCode()."/";
        $bucket = $video->getBucket();

        $basedir = $this->getTargetDirectory();

        // supprimer les fichiers source mp4
        $filename = $basedir . "/" .$video->getCode().".mp4";
        // fichier video a supprimer
        if(file_exists($filename)){
            @unlink($filename);
        }

        $result["status"] = true;
        $em->remove($video);
        $em->flush();

        switch ($video->getState()){
            // supprimer les fichiers chez amazon S3
            case "COMPLETE":
                $r = $s3Service->deleteObject($bucket,$prefix);
                $result["payload"] = $r;
                break;

            // annulation de la tache de transcodage dans mediaconvert
            case "SUBMITTED":
            case "pending":
            case "PROGRESSING":
                $this->forward("@CoaVideolibrary/Controller/VideolibraryController::cancelJob",["code"=>$video->getCode()]);
                break;
        }

        return $this->json($result);
    }

    /**
     * @Route("/{code}/cancel-job", name="cancel_job", methods={"POST"})
     * @IsGranted("ROLE_VIDEOLIBRARY_DELETE")
     *
     * annulation d'un tâche de transcodage
     */
    public function cancelJob(Request $request, MediaConvertService $mediaConvert, string $code): Response
    {
        $em = $this->getDoctrine()->getManager();
        $video = $this->getVideo($code);
        $result = ["status"=>false];
        $jobId = $video->getJobRef();

        $basedir = $this->getTargetDirectory();

        $filename = $basedir . "/" .$video->getCode().".mp4";
        // fichier video a supprimer
        if(file_exists($filename)){
            @unlink($filename);
        }

        $video->setState("CANCELED");
        $em->flush();

        if($jobId){
            $job = $mediaConvert->getJob($jobId);
            // on annule une tâche, quand celle-ci a l'un status suivant
            if(in_array($job["data"]["status"],["SUBMITTED","PROGRESSING","pending"])){
                $r = $mediaConvert->cancelJob($video->getJobRef());
            }
        }
        return $this->json($result);
    }

    /**
     * @Route("/{code}/screenshots", name="show_screenshots", methods={"GET"})
     * affichage des vignettes d'une video
     */
    public function getScreenshot(Request $request, string $code): Response
    {
        $video = $this->getVideo($code);
        $response = $this->render("@CoaVideolibrary/home/screenshot-item-render.html.twig", ["video"=>$video]);
        $response->headers->set("Cache-Control","public, max-age=3600");
        return  $response;
    }



    /**
     * @Route("/{code}/save-duration", name="save_media_duration", methods={"POST"})
     * modification de la durée d'un media
     */
    public function saveMediaDuration(Request $request, AwsS3Url $awsS3Url, string $code): Response
    {
        $video = $this->getVideo($code);
        $em = $this->getDoctrine()->getManager();

        $result = ["status"=>false];

        $duree = explode(":",trim($request->request->get("duration")));
        $h = 0;
        $m = 0;
        $s = 0;
        if(count($duree) == 3){
            list($h,$m,$s) = $duree;
        }
        else if(count($duree) == 2){
            list($m,$s) = $duree;
        }
        else{
            return $this->json([
                "status"=>false,
                "code" => 400,
                "message" => "veuiller envoyer une durée valide hh:mm:ss"
            ],200);
        }

        $duration = new \DateInterval(sprintf('PT%sH%sM%sS',$h,$m,$s));
        $seconds = $duration->days*86400 + $duration->h*3600
            + $duration->i*60 + $duration->s;

        $video->setDuration($seconds);
        $em->persist($video);
        $em->flush();
        $result["status"] = true;
        $result["message"] = "Durée modifiée avec succès";
        return  $this->json($result);
    }

    /**
     * @Route("/{code}/update-screenshot", name="update_screenshot", methods={"POST"})
     * modification de la vignette d'une video
     */
    public function setScreenshot(Request $request, AwsS3Url $awsS3Url, string $code): Response
    {
        $video = $this->getVideo($code);
        $result = ["status"=>false];
        $key = $request->request->get("key");

        if(in_array($key,$video->getScreenshots())){
            $em = $this->getDoctrine()->getManager();
            $video->setPoster($key);
            $em->persist($video);
            $em->flush();
            $result["status"] = true;
            $result["url"] = $awsS3Url->urlBasename($key,$video);
        }
        return  $this->json($result);
    }

    /**
     * @Route("/getStatus", name="getstatus")
     */
    public function getStatus(Request $request, MediaConvertService $mediaConvert, CoaVideolibraryService $coaVideolibrary): Response
    {
        $em = $this->getDoctrine()->getManager();
        $rep = $em->getRepository($this->getParameter("coa_videolibrary.video_entity"));
        $maxResults = $request->query->get("maxResults",20);
        $result = [];

        if($_ENV["APP_ENV"] == "dev"){
            $result = $coaVideolibrary->getStatus($maxResults);
        }
        else{
            $videos = $rep->findBy([],["id"=>"DESC"],$maxResults);
            $result["payload"] = array_map(function ($el){
                return [
                    "id"=>$el->getJobRef(),
                    "status"=>$el->getState(),
                    "startTime"=>$el->getjobStartTime(),
                    "finishTime"=>$el->getjobFinishTime(),
                    "submitTime"=>$el->getjobSubmitTime(),
                    "duration"=>$el->getDuration(),
                    "duration_formated"=> gmdate('H:i:s', $el->getDuration()),
                    "jobPercent"=>$el->getJobPercent(),
                    "html"=>$this->renderView("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$el]])
                ];
            },$videos);
        }
        return  $this->json($result);
    }

    /**
     * @Route("/upload", name="upload")
     * @IsGranted("ROLE_VIDEOLIBRARY_UPLOAD")
     */
    public function upload(Request $request, MediaConvertService $mediaConvert,
                           Packages $packages, CoaVideolibraryService $coaVideolibrary, EventDispatcherInterface $dispatcher): Response
    {
        $em = $this->getDoctrine()->getManager();
        $video_entity = $this->getParameter("coa_videolibrary.video_entity");
        $rep = $em->getRepository($video_entity);
        $encrypted = filter_var($request->request->get('encryption',true),FILTER_VALIDATE_BOOLEAN);
        $usefor = strtolower($request->request->get('usefor',''));
        $usefor = in_array($usefor,["film","episode","clip"]) ? $usefor : "episode";

        $targetDirectory = $this->getTargetDirectory();

        $result = [
            "status" => "fails",
        ];
        $file = $request->files->get("file");
        $video_id = $request->request->get("video_id");

        $content_range = $request->headers->get("content-range");
        list($chunk_range, $total_size) = explode("/", substr($content_range, 5));
        list($chunk_range_start, $chunk_range_end) = explode("-", $chunk_range);
        $total_size = intval($total_size);
        $chunk_range_start = intval($chunk_range_start);
        $chunk_range_end = intval($chunk_range_end);
        $is_end = ($chunk_range_end + 1 == $total_size);

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $file_length = $file->getSize();

        if ($video_id) {

            if (!($video = $rep->findOneBy(["code"=>$video_id]))) {
                $result['logs'] = "impossible de traiter cette requete";
                $result['code'] = 404;
                return $this->json($result,404);
            }

            $code = $video->getCode();
            $chunk = $file->getContent();
            $filepath = sprintf($targetDirectory . "/%s.mp4", $code);

            # on stock le fichier en local uniquement lorsque les stockage distant est desactivé
            if(!filter_var(@$_ENV['FOREIGN_DYNAMIC_STORAGE'], FILTER_VALIDATE_BOOLEAN)){
                file_put_contents($filepath, $chunk, FILE_APPEND);
            }

            $video->setFileSize($video->getFileSize() + $file_length);
            $video->setEncrypted($encrypted);
            $video->setUseFor($usefor);

            if($is_end) {
                $video->setState("pending");
            }

            $result["video_id"] = $video->getCode();
            $result["status"] = "downloading";

            // new: event "coa_videolibrary.upload" is emitted
            $event = new MultipartUploadEvent($video,$chunk,$total_size, $chunk_range_start, $chunk_range_end);
            $dispatcher->dispatch($event,"coa_videolibrary.multipartupload");

            if ($is_end) {

                // new: event "coa_videolibrary.upload" is emitted
                $event = new MultipartUploadEvent($video,null,$total_size, $chunk_range_start, $chunk_range_end);
                $dispatcher->dispatch($event,"coa_videolibrary.multipartupload");

                $result['status'] = "success";
                $key_baseurl = $this->getParameter("coa_videolibrary.hls_key_baseurl");
                $baseurl = $request->getSchemeAndHttpHost();

                if(!$key_baseurl){
                    $key_baseurl = $baseurl;
                }
                else{
                    $baseurl = $key_baseurl;
                }

                if($_ENV["APP_ENV"] == "prod"){
                    $baseurl = $request->getSchemeAndHttpHost();
                }

                $coaVideolibrary->transcode($video,$baseurl,$key_baseurl);
                $result["html"] = $this->renderView("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$video]]);
            }


            $em->persist($video);
            $em->flush();
        }
        else{

            if ($file->getMimeType() !== "video/mp4") {
                $result['log'] = sprintf("Veuillez utiliser un fichier mp4, %s n'est pas un fichier valide", $originalFilename);
                return $this->json($result,400);
            }

            $code = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(32,$ok))),"="),0,32);
            if(($code_prefix = $this->getParameter("coa_videolibrary.prefix"))){
                $code = sprintf("%s_%s",$code_prefix,$code);
            }

            $chunk = $file->getContent();
            $filepath = sprintf($targetDirectory . "/%s.mp4", $code);

            # on stock le fichier en local uniquement lorsque les stockage distant est desactivé
            if(!filter_var(@$_ENV['FOREIGN_DYNAMIC_STORAGE'], FILTER_VALIDATE_BOOLEAN)){
                file_put_contents($filepath, $chunk, FILE_APPEND);
            }

            $video = new $video_entity();
            $video->setCode($code);
            $video->setOriginalFilename($originalFilename);
            $video->setFileSize($file_length);
            $video->setState("downloading");
            $video->setIsTranscoded(false);
            $video->setPoster(null);
            $video->setScreenshots(null);
            $video->setWebvtt(null);
            $video->setManifest(null);
            $video->setDuration(null);
            $video->setCreatedAt(new \DateTimeImmutable());
            $video->setAuthor($this->getUser());
            $video->setEncrypted($encrypted);
            $video->setUseFor($usefor);

            $em->persist($video);
            $em->flush();
            $result["video_id"] = $video->getCode();
            $result['status'] = "start";

            // new: event "coa_videolibrary.upload" is emitted
            $event = new MultipartUploadEvent($video, $chunk, $total_size, $chunk_range_start, $chunk_range_end);
            $dispatcher->dispatch($event,"coa_videolibrary.multipartupload");
        }
        return $this->json($result);
    }


    /**
     * @Route("/ftpsync", name="ftpsync", methods={"POST"})
     * @IsGranted("ROLE_VIDEOLIBRARY_UPLOAD")
     * synchronisation du dossier coa_videolibrary_ftp
     */
    public function ftpsync(Request $request, CoaVideolibraryService $coaVideolibrary): Response
    {
        $result = ["status"=>true];
        $coaVideolibrary->FtpSync();
        return  $this->json($result);
    }
}
