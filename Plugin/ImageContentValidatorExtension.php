<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageContentValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\Phrase;

/**
 * Validate that the uploaded filename has a safe image extension.
 *
 * The core ImageContentValidator checks MIME type and forbidden characters
 * but never validates the file extension — a polyglot file can pass MIME
 * validation while carrying a .php (or .phtml, .phar, etc.) extension.
 */
class ImageContentValidatorExtension
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png'];

    /**
     * @var IoFile
     */
    private IoFile $ioFile;

    /**
     * @param IoFile $ioFile
     */
    public function __construct(IoFile $ioFile)
    {
        $this->ioFile = $ioFile;
    }

    /**
     * After core validation passes, additionally reject dangerous file extensions.
     *
     * @param ImageContentValidator $subject
     * @param bool $result
     * @param ImageContentInterface $imageContent
     * @return bool
     * @throws InputException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsValid(
        ImageContentValidator $subject,
        bool $result,
        ImageContentInterface $imageContent
    ): bool {
        $fileName = $imageContent->getName();
        $pathInfo = $this->ioFile->getPathInfo($fileName);
        $extension = strtolower($pathInfo['extension'] ?? '');

        // Normalize filename
        $fileName = strtolower($fileName);

        // 🚨 1. Block dangerous substrings anywhere
        $blocked = ['.php', '.phtml', '.phar', '.php5', '.php7'];
        foreach ($blocked as $bad) {
            if (strpos($fileName, $bad) !== false) {
                throw new InputException(
                    new Phrase('File name contains forbidden extension pattern.')
                );
            }
        }

        // 🚨 2. Allow only safe characters
        if (!preg_match('/^[a-z0-9._-]+$/', $fileName)) {
            throw new InputException(
                new Phrase('Invalid file name format.')
            );
        }

        // 🚨 3. Enforce single extension
        if (substr_count($fileName, '.') !== 1) {
            throw new InputException(
                new Phrase('Multiple extensions are not allowed.')
            );
        }

        if ($extension && !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InputException(
                new Phrase('The image file extension "%1" is not allowed.', [$extension])
            );
        }

        return $result;
    }
}
