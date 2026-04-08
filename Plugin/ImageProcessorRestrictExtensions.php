<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageProcessor;
use Magento\Framework\Api\Uploader;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Phrase;

/**
 * Enforce an allowlist of file extensions before ImageProcessor saves uploaded files.
 *
 * Mitigates PolyShell (APSB25-94): the core ImageProcessor never calls
 * setAllowedExtensions() on the Uploader, so any extension — including .php — is accepted.
 */
class ImageProcessorRestrictExtensions
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png'];

    /**
     * @var Uploader
     */
    private Uploader $uploader;

    /**
     * @param Uploader $uploader
     */
    public function __construct(Uploader $uploader)
    {
        $this->uploader = $uploader;
    }

    /**
     * Before processImageContent, lock the uploader to image-only extensions.
     *
     * @param ImageProcessor $subject
     * @param string $entityType
     * @param ImageContentInterface $imageContent
     * @return null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeProcessImageContent(
        ImageProcessor $subject,
        $entityType,
        $imageContent
    ) {
        $fileContent = base64_decode($imageContent->getBase64EncodedData(), true);

        if ($fileContent === false) {
            throw new InputException(new Phrase('Invalid base64 data.'));
        }

        // Detect real MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->buffer($fileContent);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

        if (!in_array($realMime, $allowedMimes, true)) {
            throw new InputException(
                new Phrase('Invalid real MIME type detected.')
            );
        }

        $this->uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
        return null;
    }
}
