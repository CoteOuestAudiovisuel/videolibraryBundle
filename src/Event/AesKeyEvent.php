<?php
namespace Coa\VideolibraryBundle\Event;
use Symfony\Contracts\EventDispatcher\Event;

class AesKeyEvent extends Event {

    private string $filename;
    private string $value;

    public function __construct(string $filename, string $value){
        $this->filename = $filename;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getFilename(): string{
        return $this->filename;
    }

    /**
     * @param string $filename
     */
    public function setFilename(string $filename): void{
        $this->filename = $filename;
    }

    /**
     * @return string
     */
    public function getValue(): string{
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): void{
        $this->value = $value;
    }
}