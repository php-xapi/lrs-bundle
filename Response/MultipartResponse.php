<?php

namespace XApi\LrsBundle\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MultipartResponse extends Response
{
    protected $subtype;
    protected $boundary;
    protected $statementPart;
    /**
     * @var Response[]
     */
    protected $parts;

    public function __construct(JsonResponse $statementPart, array $attachmentsParts = array(), $status = 200, array $headers = array(), $subtype = null)
    {
        parent::__construct(null, $status, $headers);

        if (null === $subtype) {
            $subtype = 'mixed';
        }

        $this->subtype = $subtype;
        $this->boundary = uniqid('', true);
        $this->statementPart = $statementPart;

        $this->setAttachmentsParts($attachmentsParts);
    }

    public function addAttachmentPart(AttachmentResponse $part)
    {
        $this->parts[] = $part;

        return $this;
    }

    public function setAttachmentsParts(array $attachmentsParts)
    {
        $this->parts = array($this->statementPart);

        foreach ($attachmentsParts as $part) {
            $this->addAttachmentPart($part);
        }

        return $this;
    }

    public function prepare(Request $request)
    {
        foreach ($this->parts as $part) {
            $part->prepare($request);
        }

        $this->headers->set('Content-Type', sprintf('multipart/%s; boundary="%s"', $this->subtype, $this->boundary));
        $this->headers->set('Transfer-Encoding', 'chunked');

        return parent::prepare($request);
    }

    public function sendContent()
    {
        $content = '';
        foreach ($this->parts as $part) {
            $content .= sprintf('--%s', $this->boundary)."\r\n";
            $content .= $part->headers."\r\n";
            $content .= $part->getContent();
            $content .= "\r\n";
        }

        $content .= sprintf('--%s--', $this->boundary)."\r\n";

        echo $content;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException when the content is not null
     */
    public function setContent($content)
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a MultipartResponse instance.');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return false
     */
    public function getContent()
    {
        return false;
    }
}
