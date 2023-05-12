<?php
namespace Coa\MessengerBundle\Messenger\Message;
use Coa\MessengerBundle\Messenger\Hydrator;


/**
 * @author Zacharie Assagou <zacharie.assagou@coteouest.ci>
 */
class AwsSqsNativeMessage extends Hydrator
{
    CONST KEYS = "Type MessageId TopicArn Message Timestamp SignatureVersion Signature SigningCertURL UnsubscribeURL";

    private string $type;
    private string $messageId;
    private string $topicArn;
    private string $message;
    private string $timestamp;
    private string $signatureVersion;
    private string $signature;
    private string $signingCertURL;
    private string $unsubscribeURL;

    /**
     * @param array $data
     */
    public function __construct(array $data = []){
        parent::__construct($data);
    }

    /**
     * @return string
     */
    public function getType(): string{
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void{
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getMessageId(): string{
        return $this->messageId;
    }

    /**
     * @param string $messageId
     */
    public function setMessageId(string $messageId): void{
        $this->messageId = $messageId;
    }

    /**
     * @return string
     */
    public function getTopicArn(): string{
        return $this->topicArn;
    }

    /**
     * @param string $topicArn
     */
    public function setTopicArn(string $topicArn): void{
        $this->topicArn = $topicArn;
    }

    /**
     * @return string
     */
    public function getMessage(): string{
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message): void{
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getTimestamp(): string{
        return $this->timestamp;
    }

    /**
     * @param string $timestamp
     */
    public function setTimestamp(string $timestamp): void{
        $this->timestamp = $timestamp;
    }

    /**
     * @return string
     */
    public function getSignatureVersion(): string{
        return $this->signatureVersion;
    }

    /**
     * @param string $signatureVersion
     */
    public function setSignatureVersion(string $signatureVersion): void{
        $this->signatureVersion = $signatureVersion;
    }

    /**
     * @return string
     */
    public function getSignature(): string{
        return $this->signature;
    }

    /**
     * @param string $signature
     */
    public function setSignature(string $signature): void{
        $this->signature = $signature;
    }

    /**
     * @return string
     */
    public function getSigningCertURL(): string{
        return $this->signingCertURL;
    }

    /**
     * @param string $signingCertURL
     */
    public function setSigningCertURL(string $signingCertURL): void{
        $this->signingCertURL = $signingCertURL;
    }

    /**
     * @return string
     */
    public function getUnsubscribeURL(): string{
        return $this->unsubscribeURL;
    }

    /**
     * @param string $unsubscribeURL
     */
    public function setUnsubscribeURL(string $unsubscribeURL): void{
        $this->unsubscribeURL = $unsubscribeURL;
    }

}