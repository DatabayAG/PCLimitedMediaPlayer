<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ILIAS\HTTP\Services;
use ilInitialisation;
use ilWACPath;
use ILIAS\FileDelivery\Delivery;
use ILIAS\DI\Exceptions\Exception;
use ilIniFile;

/**
 * Delivery of media in the resource storage
 * Can hopefully be replaced by FileDelivery service with signed delivery in ILIAS 9
 */
class MediumDelivery
{
    private const SCRIPT = "./Customizing/global/plugins/Services/COPage/PageComponent/PCLimitedMediaPlayer/deliver.php";

    private Services $http;

    public function __construct(Services $http)
    {
        $this->http = $http;
    }

    public function getUrl(?string $file_id): ?string
    {
        if (empty($file_id)) {
            return null;
        }
        return self::SCRIPT . '?file_id=' . urlencode($file_id) . '&signature=' . urlencode($this->signature($file_id));
    }

    public function handleRequest(): void
    {
        ilInitialisation::handleErrorReporting();

        try {
            $params = $this->http->request()->getQueryParams();

            $file_id = $params['file_id'] ?? '';
            $signature = $params['signature'] ?? '';

            if ($signature !== $this->signature($file_id)) {
                throw new Exception("Invalid signature $signature with session_id " . session_id());
            }

            $this->deliver($this->getInternalPath($file_id));

        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function signature(string $file_id): string
    {
        return hash('sha256', $file_id . session_id());
    }

    private function getInternalPath(string $file_id): string
    {
        $file_id = str_replace('-', '', $file_id);
        if (!preg_match('/[a-f0-9]{32}/', $file_id)) {
            throw new Exception("File ID '$file_id' is not a valid file id");
        }

        $ini = new ilIniFile("./ilias.ini.php");
        $ini->read();
        $data_dir = $ini->readVariable("clients", "datadir");
        $client_id = $ini->readVariable("clients", "default");

        $path = $data_dir . '/' . $client_id
            . '/storage/fsv2'
            . '/' . substr($file_id, 0, 3)
            . '/' . substr($file_id, 3, 3)
            . '/' . substr($file_id, 6, 3)
            . '/' . substr($file_id, 9, 23)
            . '/1/data';

        if (!is_file($path) && is_readable($path)) {
            throw new Exception("File is not readable");
        }

        return $path;
    }

    private function error(string $message): void
    {
        $response = $this->http->response()->withStatus(500);

        /** @var \Psr\Http\Message\StreamInterface $stream */
        $stream = $response->getBody();
        $stream->write($message);

        $this->http->saveResponse($response);
        $this->http->sendResponse();
    }

    private function deliver(string $path): void
    {
        // don't normalize because this would check if path is in web data directory
        $wac_path = new ilWACPath($path, false);

        $delivery = new Delivery($path, $this->http);
        $delivery->setCache(true);
        $delivery->setDisposition(Delivery::DISP_INLINE);

        if ($wac_path->isStreamable()) {
            $delivery->stream();
        } else {
            $delivery->deliver();
        }
    }
}