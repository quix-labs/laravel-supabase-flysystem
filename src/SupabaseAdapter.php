<?php

namespace Alancolant\FlysystemSupabaseAdapter;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

class SupabaseAdapter implements FilesystemAdapter
{
    protected const EMPTY_FOLDER_PLACEHOLDER_NAME = '.emptyFolderPlaceholder';

    private Config $config;

    private string $endpoint;

    private string $bucket;

    private string $key;

    private PendingRequest $httpClient;

    public function __construct(array $config)
    {
        $this->config = new Config($config);
        $endpoint = $this->config->get('endpoint') ?? throw new \LogicException('endpoint is not specified');
        $this->endpoint = $endpoint.'/storage/v1';
        $this->bucket = $this->config->get('bucket') ?? throw new \LogicException('bucket is not specified');
        $this->key = $this->config->get('key') ?? throw new \LogicException('key is not specified');

        $this->httpClient = Http::baseUrl($this->endpoint)->withHeaders([
            'Authorization' => "Bearer {$this->key}",
            'apiKey' => $this->key,
            'Content-Type' => 'application/json',
        ]);
    }

    public function fileExists(string $path): bool
    {
        return $this->httpClient->head("/object/{$this->bucket}/{$path}")->successful();
    }

    public function directoryExists(string $path): bool
    {
        $response = $this->httpClient->post("/object/list/{$this->bucket}", ['prefix' => $path, 'limit' => 1]);

        return count($response->json()) >= 1;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $res = (clone $this->httpClient)->withHeaders([
            'x-upsert' => 'true',
            'Cache-Control' => 3600,
        ])->withBody($contents, (new \finfo(FILEINFO_MIME))->buffer($contents))
            ->post("/object/{$this->bucket}/{$path}");

        // Delete empty placeholder file if not specified directly
        if ($res->successful() && $res->json('Id') !== null) {
            $filename = pathinfo($path, PATHINFO_BASENAME);
            if ($filename !== static::EMPTY_FOLDER_PLACEHOLDER_NAME) {
                $this->delete(str_replace($filename, static::EMPTY_FOLDER_PLACEHOLDER_NAME, $path));
            }
        }

        // If duplicate, delete file and recreate
        if ($res->status() === 400 && $res->json('statusCode') === '409') {
            $this->delete($path);
            $this->write($path, $contents, $config);

            return;
        }

        if (! $res->successful() || $res->json('Id') === null) {
            throw UnableToWriteFile::atLocation($path);
        }
    }

    /**
     * @param  resource  $contents
     *
     * @throws FilesystemException
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $binaryContent = stream_get_contents($contents);
        $this->write($path, $binaryContent, $config);
        fclose($contents);
    }

    public function read(string $path): string
    {

        $response = $this->httpClient->get("/object/{$this->bucket}/{$path}");

        if (! $response->successful()) {
            throw UnableToReadFile::fromLocation($path);
        }

        return $response->body();
    }

    public function readStream(string $path)
    {
        $response = (clone $this->httpClient)->withOptions(['stream' => true])->get("/object/{$this->bucket}/{$path}");

        return Utils::streamFor($response)->detach();
    }

    public function delete(string $path): void
    {
        if (! $this->fileExists($path)) {
            return;
        }

        $res = $this->httpClient->delete("/object/{$this->bucket}", ['prefixes' => [$path]]);
        if (! $res->successful() || count($res->json()) == 0) {
            throw UnableToDeleteFile::atLocation($path);
        }
    }

    public function deleteDirectory(string $path): void
    {
        if (! $this->directoryExists($path)) {
            return;
        }
        /** @var \Generator $itemsGenerator */
        $itemsGenerator = $this->listContents($path, true);
        $prefixes = array_map(fn (StorageAttributes $item) => $item->path(), iterator_to_array($itemsGenerator));

        $res = $this->httpClient->delete("/object/{$this->bucket}", ['prefixes' => $prefixes]);
        if (! $res->successful() || count($res->json()) == 0) {
            throw UnableToDeleteDirectory::atLocation($path);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        if ($this->directoryExists($path)) {
            return;
        }

        try {
            $this->write($this->_join($path, static::EMPTY_FOLDER_PLACEHOLDER_NAME), '', $config);
        } catch (UnableToWriteFile) {
            throw UnableToCreateDirectory::atLocation($path);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, "Driver doesn't support visibility");
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToSetVisibility::atLocation($path, "Driver doesn't support visibility");
    }

    public function mimeType(string $path): FileAttributes
    {
        $item = $this->_getFileInfo($path);

        return new FileAttributes(path: $path, mimeType: Arr::get($item, 'metadata.mimetype'));
    }

    public function lastModified(string $path): FileAttributes
    {
        $item = $this->_getFileInfo($path);
        $lastModified = Arr::get($item, 'metadata.lastModified');
        $lastModified = $lastModified ? Carbon::parse($lastModified)->unix() : null;

        return new FileAttributes(path: $path, lastModified: $lastModified);
    }

    public function fileSize(string $path): FileAttributes
    {
        $item = $this->_getFileInfo($path);

        return new FileAttributes(path: $path, fileSize: Arr::get($item, 'metadata.size'));
    }

    public function listContents(string $path, bool $deep): iterable
    {

        $response = $this->httpClient->post("/object/list/{$this->bucket}", [
            'prefix' => $path,
            'limit' => 100,
            'offset' => 0,
            'sortBy' => [
                'column' => 'name',
                'order' => 'asc',
            ],
        ]);

        foreach ($response->json() as $item) {

            $itemPath = Arr::get($item, 'name');

            if (Arr::get($item, 'id') === null) {  //It's directory
                yield new DirectoryAttributes(
                    path: $this->_join($path, $itemPath),
                    extraMetadata: Arr::get($item, 'metadata') ?? [],
                );

                if (! $deep) {
                    continue;
                }
                // Recursive strategy if deep is true
                foreach ($this->listContents($this->_join($path, Arr::get($item, 'name')), true) as $subItem) {
                    yield $subItem;
                }

                continue;
            }

            $lastModified = Arr::get($item, 'metadata.lastModified');
            $lastModified = $lastModified ? Carbon::parse($lastModified)->unix() : null;

            $file = new FileAttributes(
                path: $this->_join($path, $itemPath),
                fileSize: Arr::get($item, 'metadata.size'),
                lastModified: $lastModified,
                mimeType: Arr::get($item, 'metadata.mimetype'),
                extraMetadata: Arr::except(
                    Arr::get($item, 'metadata') ?? [],
                    ['mimetype', 'size', 'contentLength', 'lastModified']
                ),
            );

            yield $file;
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $res = $this->httpClient->post('/object/move', [
            'bucketId' => $this->bucket,
            'sourceKey' => $source,
            'destinationKey' => $destination,
        ]);

        // If destination already exists, delete file and rerun
        if ($res->status() === 400 && $res->json('statusCode') === '409') {
            $this->delete($destination);
            $this->move($source, $destination, $config);

            return;
        }

        if (! $res->successful()) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $res = $this->httpClient->post('/object/copy', [
            'bucketId' => $this->bucket,
            'sourceKey' => $source,
            'destinationKey' => $destination,
        ]);

        // If destination already exists, delete file and rerun
        if ($res->status() === 400 && $res->json('statusCode') === '409') {
            $this->delete($destination);
            $this->copy($source, $destination, $config);

            return;
        }

        if (! $res->successful() || $res->json('Key') === null) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }
    }

    /**
     * @throws FilesystemException
     */
    public function getUrl(string $path): string
    {
        $defaultUrlGeneration = $this->config->get('defaultUrlGeneration', $this->config->get('public', true) ? 'public' : 'signed');
        $defaultUrlGenerationOptions = $this->config->get('defaultUrlGenerationOptions', []);

        return match ($defaultUrlGeneration) {
            'public' => $this->getPublicUrl($path, $defaultUrlGenerationOptions),
            'signed' => $this->getSignedUrl($path, $defaultUrlGenerationOptions),
            default => throw new \RuntimeException("Invalid value for \"defaultUrlGeneration\": $defaultUrlGeneration"),
        };
    }

    public function getSignedUrl(string $path, array $options = []): string
    {
        $options['expiresIn'] = $options['expiresIn'] ?? $this->config->get('signedUrlExpires', 3600);
        $_queryString = '';

        if (Arr::get($options, 'transform') && ! str_starts_with($this->mimeType($path)->mimeType(), 'image/')) {
            unset($options['transform']);
        }

        if (Arr::get($options, 'download')) {
            $_queryString = '&download';
            unset($options['download']);
        }

        $res = $this->httpClient->post("/object/sign/{$this->bucket}/{$path}", $options);
        if (! $res->successful() || $res->json('signedURL') === null) {
            throw new UnableToGenerateTemporaryUrl($res->body(), $path);
        }

        $url = $this->config->get('url', $this->endpoint);

        return urldecode($this->_join($url, $res->json('signedURL')).$_queryString);
    }

    public function getPublicUrl(string $path, array $options = []): string
    {
        $public = $this->config->get('public', true);
        if (! $public) {
            throw new \RuntimeException('Your filesystem is not configured to allow public url');
        }
        $url = $this->config->get('url', $this->endpoint);
        $renderPath = 'object';

        $_queryParams = [];

        if (Arr::get($options, 'transform') && str_starts_with($this->mimeType($path)->mimeType(), 'image/')) {
            $renderPath = 'render/image';
            $_queryParams = array_merge($_queryParams, $options['transform']);
        }

        if (Arr::get($options, 'download')) {
            $_queryParams = array_merge($_queryParams, ['download' => null]);
        }

        $_queryString = collect($_queryParams)
            ->map(fn ($value, $key) => $key.($value ? "=$value" : ''))
            ->join('&');

        if ($_queryString != '') {
            $_queryString = "?$_queryString";
        }

        return urldecode($this->_join($url, $renderPath, '/public/', $this->bucket, $path).$_queryString);
    }

    private function _join(...$paths): string
    {
        return collect($paths)
            ->map(fn (string $path) => str($path)->rtrim('/')->ltrim('/')->toString())
            ->filter()
            ->join('/');
    }

    private function _getFileInfo(string $path): array
    {
        $folderPath = pathinfo($path, PATHINFO_DIRNAME);
        $folderPath = $folderPath === '.' ? '' : $folderPath;

        $filename = pathinfo($path, PATHINFO_BASENAME);

        $response = $this->httpClient->post("/object/list/{$this->bucket}", [
            'prefix' => $folderPath,
            'limit' => 100,
            'search' => $filename,
        ]);

        if (! $response->successful() || count($response->json()) === 0) {
            throw UnableToReadFile::fromLocation($path);
        }

        $item = collect($response->json())->firstWhere('name', $filename);
        if (! $item) {
            throw UnableToReadFile::fromLocation($path);
        }

        return $item;
    }
}
