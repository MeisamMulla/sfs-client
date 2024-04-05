# SFS Client

The SFS Client is a PHP library for communicating with a StretchFS server to manage content and jobs. It leverages GuzzleHttp for HTTP requests, providing a simple interface for file uploads, downloads, and job management.

[StretchFS](https://github.com/nullivex/stretchfs-sdk)

## Installation

Install the package via Composer:

```bash
composer require meisam-mulla/sfs-client
```

## Usage
First, include the SFS Client in your project:

```php
use MeisamMulla\SfsClient\StretchFS;
```

Initialize the client with your server's configuration:

```php
$client = new StretchFS([
    'username' => 'your_username', // optional if you have a token
    'password' => 'your_password', // optional if you have a token
    'domain' => 'sfsserver.net', // SFS server
    'port' => 8161, // Default port
    'token' => 'your_token', // If you already have a token
]);
```

## Authentication
Generate a new token:

```php
$token = $client->generateToken();
```
Destroy a token:

```php
$client->destroyToken(token: 'iu23gn43g2i4i');
```

## Folder Management
Create a folder:
```php
$client->folderCreate(folderPath: '/test');
```

Delete a folder:
```php
$client->folderDelete(folderPath: '/test');
```

List all files in a directory:
```php
$client->fileList(folderPath: '/');
```

## File Management
Upload a file from path
```php
$client->fileUpload(filePath: '/home/user/somefile.txt', folderPath: '/');
```

Upload a file from string
```php
$client->fileUploadFromString(filePath: '/text.txt', contents: 'contents of text.txt');
```

Download a file
```php
$contents = $client->fileDownload(filePath: '/text.txt');
```

Get file details
```php
$contents = $client->fileDetail(filePath: '/text.txt');
```

Delete a file
```php
$client->fileDelete(filePath: '/text.txt');
```

## Temporary URLs
Create hash for a temporary url
```php
$purchaseToken = $client->contentPurchase(hash: '2i34ug2b3u4t23b4o82y3t48723458295b3y4i3', seconds: 3600);
```
Revoke temporary url hash
```php
$hash = $client->contentPurchaseRemove(purchaseToken: 'a54d88e06612d820bc3be72877c74f257b561b19');
```

## Job Management
Create a job

```php
$job = $client->jobCreate(description: [
    "callback" => [
        'request' => [
            'method' => 'GET',
            'url' => "http://some.url/job.complete",
        ],
    ],
    "resource" => [
        [
            "name" => 'somefile.zip',
            "request" => [
                "method" => "GET",
                "url" => "https://url.to/file.zip",
            ]
        ]
    ]
], priority: 12, category: 'ingest');
```

Update a job
```php
$client->jobUpdate(handle: '5sE4674U4ft2', changes: [
    "resource" => [
        [
            "name" => 'somefile.zip',
            "request" => [
                "method" => "GET",
                "url" => "https://url.to/file.zip",
            ]
        ]
    ]
]);
```
Start a job
```php
$client->jobStart(handle: 'FQukh4sIMN4F');
```
Get job details
```php
$client->jobDetail(handle: 'FQukh4sIMN4F');
```

Abort a job
```php
$client->jobAbort(handle: 'FQukh4sIMN4F');
```

Retry a job
```php
$client->jobRetry(handle: 'FQukh4sIMN4F');
```

Delete a job
```php
$client->jobRemove(handle: 'FQukh4sIMN4F');
```

Check if content exists in a job temporary directory
```php
$client->jobContentExists(handle: 'FQukh4sIMN4F', file: 'file.zip');
```
