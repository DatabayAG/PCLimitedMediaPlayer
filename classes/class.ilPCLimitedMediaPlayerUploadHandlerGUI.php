<?php

declare(strict_types=1);

use ILIAS\FileUpload\Handler\BasicHandlerResult;

/**
 * @ilCtrl_isCalledBy ilPCLimitedMediaPlayerUploadHandlerGUI: ilPCLimitedMediaPlayerPluginGUI
 */
class ilPCLimitedMediaPlayerUploadHandlerGUI extends ilCtrlAwareStorageUploadHandler
{
    private string $purpose = 'upload';

    public function setPurpose(string $purpose): self
    {
        $this->purpose = $purpose;
        return $this;
    }

    public function setFileId(string $file_id): void
    {
        ilSession::set(self::class . '::' . $this->purpose, $file_id);
    }

    public function getFileId(): string
    {
        return (string) ilSession::get(self::class . '::' . $this->purpose);
    }

    public function getUploadURL(): string
    {
        $this->setParams();
        return $this->ctrl->getLinkTarget($this, self::CMD_UPLOAD);
    }

    public function getExistingFileInfoURL(): string
    {
        $this->setParams();
        return $this->ctrl->getLinkTarget($this, self::CMD_INFO);
    }

    public function getFileRemovalURL(): string
    {
        $this->setParams();
        return $this->ctrl->getLinkTarget($this, self::CMD_REMOVE);
    }

    protected function getUploadResult(): BasicHandlerResult
    {
        $result = parent::getUploadResult();
        $this->setFileId($result->getFileIdentifier());
        return $result;
    }

    /**
     * Set parameters expected by the page editor
     * Otherwise a return to parent is called
     * @see ilPageEditorGUI::executeCommand()
     */
    private function setParams()
    {
        $this->ctrl->setParameter($this, 'purpose', $this->purpose);
        $this->ctrl->setParameter($this, 'cname', 'Plugged');
    }

    public function getFileIdentifierParameterName(): string
    {
        return $this->purpose;
    }
}
