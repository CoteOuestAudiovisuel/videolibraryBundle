<?php
namespace Coa\VideolibraryBundle\Event;
use Symfony\Contracts\EventDispatcher\Event;

class MultipartUploadEvent extends Event {

    private $data;
    private int $part;
    private int $size;
    private int $rangeStart;
    private int $rangeEnd;
    private $chunk;
    private ?string $location;

    public function __construct($data, $chunk = null,  int $part = 1, int $size = -1, int $rangeStart = -1, int $rangeEnd = -1){
        $this->data = $data;
        $this->part = $part;
        $this->size = $size;
        $this->rangeStart = $rangeStart;
        $this->rangeEnd = $rangeEnd;
        $this->chunk = $chunk;
    }

    /**
     * @return string
     */
    public function getData(){
        return $this->data;
    }

    /**
     * @param string $data
     */
    public function setData($data): self{
        $this->data = $data;
        return $this;
    }

    /**
     * @return int
     */
    public function getPart(): int{
        return $this->part;
    }

    /**
     * @param int $part
     */
    public function setPart(int $part): self{
        $this->part = $part;
        return $this;
    }

    /**
     * @return int
     */
    public function getSize(): int{
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize(int $size): self{
        $this->size = $size;
        return $this;
    }

    /**
     * @return int
     */
    public function getRangeStart(): int{
        return $this->rangeStart;
    }

    /**
     * @param int $rangeStart
     */
    public function setRangeStart(int $rangeStart): self{
        $this->rangeStart = $rangeStart;
        return $this;
    }

    /**
     * @return int
     */
    public function getRangeEnd(): int{
        return $this->rangeEnd;
    }

    /**
     * @param int $rangeEnd
     */
    public function setRangeEnd(int $rangeEnd): self{
        $this->rangeEnd = $rangeEnd;
        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getChunk(){
        return $this->chunk;
    }

    /**
     * @param mixed|null $chunk
     */
    public function setChunk($chunk = null): self{
        $this->chunk = $chunk;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLocation(): ?string{
        return $this->location;
    }

    /**
     * @param string|null $location
     */
    public function setLocation(?string $location): void{
        $this->location = $location;
    }
}