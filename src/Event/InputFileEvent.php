<?php
namespace Coa\VideolibraryBundle\Event;
use Symfony\Contracts\EventDispatcher\Event;

class InputFileEvent extends Event {

    private string $inputUrl;
    private string $awsInputUrl;
    private string $keycode;
    private string $bucket;

    public function __construct(string $inputUrl, string $keycode, string $bucket){
        $this->inputUrl = $inputUrl;
        $this->awsInputUrl = $inputUrl;
        $this->keycode = $keycode;
        $this->bucket = $bucket;
    }

    /**
     * @return string
     */
    public function getInputUrl(): string{
        return $this->inputUrl;
    }

    /**
     * @param string $inputUrl
     */
    public function setInputUrl(string $inputUrl): void{
        $this->inputUrl = $inputUrl;
    }

    /**
     * @return string
     */
    public function getAwsInputUrl(): string{
        return $this->awsInputUrl;
    }

    /**
     * @param string $awsInputUrl
     */
    public function setAwsInputUrl(string $awsInputUrl): void{
        $this->awsInputUrl = $awsInputUrl;
    }

    /**
     * @return string
     */
    public function getKeycode(): string{
        return $this->keycode;
    }

    /**
     * @param string $keycode
     */
    public function setKeycode(string $keycode): void{
        $this->keycode = $keycode;
    }

    /**
     * @return string
     */
    public function getBucket(): string{
        return $this->bucket;
    }

    /**
     * @param string $bucket
     */
    public function setBucket(string $bucket): void{
        $this->bucket = $bucket;
    }

}