<?php

namespace XApi\LrsBundle\Response;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Model\Attachment;

class AttachmentResponse extends Response
{
    protected $attachment;

    public function __construct(Attachment $attachment)
    {
        parent::__construct(null);

        $this->attachment = $attachment;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(Request $request)
    {
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', $this->attachment->getContentType());
        }

        $this->headers->set('Content-Transfer-Encoding', 'binary');
        $this->headers->set('X-Experience-API-Hash', $this->attachment->getSha2());
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException
     */
    public function sendContent()
    {
        throw new \LogicException('An AttachmentResponse is only meant to be part of a multipart Response.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException when the content is not null
     */
    public function setContent($content)
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on an AttachmentResponse instance.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return $this->attachment->getContent();
    }
}
