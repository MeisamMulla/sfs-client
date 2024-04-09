<?php

namespace MeisamMulla\SfsClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use stdClass;

class StretchFS
{
    /**
     * @var stdClass Options for the Prism instance.
     */
    private $opts;

    /**
     * @var Client The HTTP client for making requests.
     */
    private $client;

    /**
     * @var string Authentication token for the session.
     */
    private $token = null;

    /**
     * @var string Session token for the session.
     */
    private $sessionToken = null;

    /**
     * Prism constructor.
     * Initializes the client and sets up the options.
     *
     * @param  array  $opts  Optional overrides for default settings.
     */
    public function __construct(array $opts = [])
    {
        $this->opts = new stdClass;
        $this->opts->username = $opts['username'] ?? null;
        $this->opts->password = $opts['password'] ?? null;
        $this->opts->token = $opts['token'] ?? null;
        $this->opts->domain = $opts['domain'] ?? 'vidcache.net';
        $this->opts->port = $opts['port'] ?? 8161;
        $this->opts->timeout = $opts['timeout'] ?? 60.0;

        $this->client = new Client([
            'base_uri' => 'https://' . $this->opts->domain . ':' . $this->opts->port . '/',
            'timeout' => $this->opts->timeout,
        ]);

        if ($this->opts->token) {
            $this->token = $this->opts->token;
        }

        if (!$this->opts->username && !$this->opts->password && !$this->token) {
            throw new Exception("Missing authentication credentials");
        }

        $this->sessionToken = md5(uniqid());
    }

    /**
     * Generate a new token
     */
    public function generateToken()
    {
        return $this->token = $this->login($this->opts->username, $this->opts->password)['token'];
    }

    /**
     * Destroy token
     *
     * @param string $token
     * @return boolean
     */
    public function destroyToken(string $token): bool
    {
        return $this->logout();
    }

    /**
     * Attempts to log in with the provided credentials.
     *
     * @param  string  $username  The username for login.
     * @param  string  $password  The password for login.
     *
     * @throws Exception on failure to log in or network errors.
     */
    protected function login($username, $password): array
    {
        try {
            $response = $this->client->post('user/login', [
                'json' => [
                    'username' => $username,
                    'password' => $password,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['session'])) {
                throw new Exception('Login failed, no session');
            }

            return $body['session'];
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    protected function logout(): bool
    {
        try {
            $response = $this->client->post('user/logout', [
                'json' => true,
                'headers' => ['X-STRETCHFS-Token' => $this->token, 'Accept' => 'application/json'],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['session'])) {
                throw new Exception('Login failed, no session');
            }

            return true;
        } catch (GuzzleException $e) {
            // Handle HTTP or network errors
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    public function contentDetail(string $hash): array
    {
        try {
            $response = $this->client->post('content/detail', [
                'json' => ['hash' => $hash],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Download content from remote url
     *
     * @param array $request
     * @param string $fileExtension
     * @return array
     */
    public function contentRetrieve(array $request, string $fileExtension): array
    {
        try {
            $response = $this->client->post('content/retrieve', [
                'json' => [
                    'request' => $request,
                    'extension' => $fileExtension,
                ],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Generate static URL
     *
     * @param string $hash
     * @param string $name
     * @return string
     */
    public function urlStatic(string $hash, string $name = 'file'): string
    {
        $encodedName = urlencode($name);

        return '//' . $this->opts->domain . '/static/' . $hash . '/' . $encodedName;
    }

    public function folderCreate(string $folderPath): array
    {
        try {
            $response = $this->client->post('file/folderCreate', [
                'json' => ['path' => $this->filePathSanitize($folderPath)],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Delete folder
     *
     * @param string $folderPath
     * @return array
     */
    public function folderDelete(string $folderPath): array
    {
        try {
            $response = $this->client->post('file/remove', [
                'json' => ['path' => $this->filePathSanitize($folderPath)],
                'headers' => ['X-STRETCHFS-Token' => $this->token, 'Accept' => 'application/json'],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Sanitize file path
     *
     * @param string $filePath
     * @return string
     */
    protected function filePathSanitize(string $filePath): string
    {
        $firstChar = substr($filePath, 0, 1);

        if ('.' == $firstChar) {
            $filePath = '/' . substr($filePath, 1);
        } else if ('/' !== $firstChar) {
            $filePath = '/' . $filePath;
        }
        return $filePath;
    }

    /**
     * List files in folder
     *
     * @param string $folderPath
     * @return array
     */
    public function fileList(string $folderPath): array
    {
        try {
            $response = $this->client->get('file/list', [
                'query' => ['path' => urlencode($this->filePathSanitize($folderPath))],
                'headers' => ['X-STRETCHFS-Token' => $this->token, 'Accept' => 'application/json'],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Upload file from path
     *
     * @param string $filePath
     * @param string $folderPath
     * @return array
     */
    public function fileUpload(string $filePath, string $folderPath = '/'): array
    {
        if (!file_exists($filePath)) {
            throw new Exception('File not found');
        }

        if (substr($folderPath, -1) !== '/') {
            $folderPath .= '/';
        }

        $fileResource = fopen($filePath, 'r');

        if (!$fileResource) {
            throw new Exception('Failed to open file');
        }

        return $this->fileUploadFromResource($fileResource, basename($filePath), $folderPath);
    }

    /**
     * Upload file from resource
     *
     * @param $resource
     * @param string $fileName
     * @param string $folderPath
     * @return array
     */
    public function fileUploadFromResource($resource, string $fileName, string $folderPath = '/'): array
    {
        if (!is_resource($resource)) {
            throw new Exception('Not a resource');
        }

        if (substr($folderPath, -1) !== '/') {
            $folderPath .= '/';
        }

        try {
            $response = $this->client->post('file/upload', [
                'headers' => ['X-STRETCHFS-Token' => $this->token, 'Accept' => 'application/json'],
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $resource,
                        'filename' => $fileName,
                    ],
                ],
                'query' => ['path' => urlencode($this->filePathSanitize($folderPath))],
            ]);

            if (is_resource($resource)) {
                fclose($resource);
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            if (is_resource($resource)) {
                fclose($resource);
            }

            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Upload file from string
     *
     * @param string $filePath
     * @param string $contents
     * @return array
     */
    public function fileUploadFromString(string $filePath, string $contents): array
    {
        try {
            $response = $this->client->post('file/upload', [
                'headers' => ['X-STRETCHFS-Token' => $this->token, 'Accept' => 'application/json'],
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $contents,
                        'filename' => basename($filePath),
                    ],
                ],
                'query' => ['path' => urlencode($this->filePathSanitize(pathinfo($filePath, PATHINFO_DIRNAME)))],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Download file from handle
     *
     * @param string $filePath
     * @param int $life
     * @return array
     */
    public function fileDownloadUrl(string $filePath, int $life = 3600): array
    {
        try {
            $response = $this->client->get('file/download', [
                'query' => [
                    'path' => $this->filePathSanitize($filePath),
                    'token' => $this->sessionToken,
                ],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token, 'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    public function fileDownload(string $filePath): string
    {
        $object = $this->fileDownloadStream($filePath);
        $contents = stream_get_contents($object);

        fclose($object);
        unset($object);

        return $contents;
    }

    /**
     * Download a file as a stream.
     *
     * @param string $filePath The path of the file to download.
     * @return resource A stream of the file contents.
     * @throws Exception If the download fails.
     */
    public function fileDownloadStream(string $filePath)
    {
        try {
            $downloadInfo = $this->fileDownloadUrl($filePath);

            if (!isset($downloadInfo['url'])) {
                throw new Exception("Missing URL in the download information.");
            }

            $response = $this->client->request('GET', $downloadInfo['url'], ['stream' => true]);

            return $response->getBody()->detach();
        } catch (GuzzleException $e) {
            throw new Exception("Failed to download file stream: " . $e->getMessage());
        }
    }

    /**
     * Get file details
     *
     * @param string $filePath
     * @return array
     */
    public function fileDetail(string $filePath): array
    {
        try {
            $response = $this->client->get('file/detail', [
                'query' => ['path' => $this->filePathSanitize($filePath)],
                'headers' => ['X-STRETCHFS-Token' => $this->token, 'Accept' => 'application/json'],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Link a file to a folder after the job has been completed
     *
     * @param string $jobHandle
     * @param string $hash
     * @param string $path
     * @return array
     */
    public function fileLink(string $jobHandle, string $hash, string $path = '/'): array
    {
        try {
            $response = $this->client->post('file/link', [
                'json' => [
                    'handle' => $jobHandle,
                    'hash' => $hash,
                    'path' => $this->filePathSanitize($path),
                ],
                'headers' => ['X-STRETCHFS-Token' => $this->token, 'Accept' => 'application/json'],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Check if content matching a hash exists
     *
     * @param string $hash
     * @return array
     */
    public function contentExists(string $hash): array
    {
        try {
            $response = $this->client->post('content/exists', [
                'json' => ['hash' => $hash],
                'headers' => ['X-STRETCHFS-Token' => $this->token, 'Accept' => 'application/json'],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Delete a file
     *
     * @param string $filePath
     * @return array
     */
    public function fileDelete(string $filePath): array
    {
        try {
            $response = $this->client->post('file/remove', [
                'json' => ['path' => $this->filePathSanitize($filePath)],
                'headers' => ['X-STRETCHFS-Token' => $this->token, 'Accept' => 'application/json'],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Create a new job
     *
     * @param  array  $description
     * @param  int|null  $priority
     * @param  string  $category
     * @return array
     */
    public function jobCreate($description, $priority = null, $category = 'resource'): array
    {
        try {
            $response = $this->client->post('job/create', [
                'json' => [
                    'description' => json_encode($description),
                    'priority' => $priority,
                    'category' => $category,
                ],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Get details of a job
     *
     * @param  string  $handle
     * @return array
     */
    public function jobDetail(string $handle): array
    {
        try {
            $response = $this->client->post('job/detail', [
                'json' => [
                    'handle' => $handle,
                ],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Update a job with the changes
     *
     * @param  string  $handle
     * @param  array  $changes
     * @return array
     */
    public function jobUpdate(string $handle, array $changes): array
    {
        try {
            $response = $this->client->post('job/update', [
                'json' => [
                    'handle' => $handle,
                    'changes' => $changes,
                ],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Start a job
     *
     * @param  string  $handle
     * @return array
     */
    public function jobStart(string $handle): array
    {
        try {
            $response = $this->client->post('job/start', [
                'json' => [
                    'handle' => $handle,
                ],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Abort a Job
     *
     * @param  string  $handle
     * @return array
     */
    public function jobAbort(string $handle): array
    {
        try {
            $response = $this->client->post('job/abort', [
                'json' => [
                    'handle' => $handle,
                ],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Retry a Job
     *
     * @param  string  $handle
     * @return array
     */
    public function jobRetry(string $handle): array
    {
        try {
            $response = $this->client->post('job/retry', [
                'json' => [
                    'handle' => $handle,
                ],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Remove a Job
     *
     * @param  string  $handle
     * @return array
     */
    public function jobRemove(string $handle): array
    {
        try {
            $response = $this->client->post('job/remove', [
                'json' => [
                    'handle' => $handle,
                ],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Check if a file exists in a job content directory
     *
     * @param  string  $handle
     * @param  string  $file
     * @return bool
     */
    public function jobContentExists(string $handle, string $file): bool
    {
        try {
            $response = $this->client->post('job/content/exists', [
                'json' => [
                    'handle' => $handle,
                    'file' => $file,
                ],
                'headers' => [
                    'X-STRETCHFS-Token' => $this->token,
                    'Accept' => 'application/json',
                ],
            ]);
            $body = json_decode($response->getBody()->getContents(), true);

            return !empty($body) && isset($body['exists']) ? $body['exists'] : false;
        } catch (GuzzleException $e) {
            throw new Exception('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Generate the url for a file in a job content directory
     *
     * @param string $handle
     * @param string $file
     * @return string
     */
    public function jobContentUrl(string $handle, string $file): string
    {
        return '//' . $this->opts->domain . ':' . $this->opts->port . '/job/content/download/' . $handle . '/' . $file;
    }
}
