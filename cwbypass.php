<?php

class Downloader
{
    protected $id_1;
    protected $id_2;
    protected $output_file;

    protected $chunk_size = (1024 ** 2) * 50;

    protected $req_id = null;

    protected $chunk = 1;
    protected $total_size = null;
    protected $total_chunks = null;

    /**
     * @var null|SplFileObject
     */
    protected $file = null;

    protected $curl;

    public function __construct($id_1, $id_2, $output_file)
    {
        $this->id_1 = $id_1;
        $this->id_2 = $id_2;
        $this->output_file = $output_file;
    }

    public static function formatFilesize($bytes, $p = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = min(floor(($bytes ? log($bytes) : 0) / log(1024)), (count($units) - 1));
        $bytes /= pow(1024, $pow);
        return round($bytes, $p) . ' ' . $units[$pow];
    }

    protected function getUrl()
    {
        $rand = mt_rand(0, 10000000);
        $bytes_start = ($this->chunk - 1) * $this->chunk_size;
        if ($this->total_chunks === null || $this->chunk < $this->total_chunks) {
            $bytes_end = ($bytes_start + $this->chunk_size) - 1;
        } else {
            $bytes_end = $this->total_size - 1;
        }

        $url = "https://zagent352.h-cdn.com/camwhores/gen/www.camwhores.tv/get_file/70/13bf19e556ec138e8995abe5c09f0a7730609d50be/{$this->id_1}/{$this->id_2}/{$this->id_2}.mp4/?rnd={$rand}&hola&hrange=%s&req_id=%s&cdn=single";

        return sprintf($url, "{$bytes_start}-{$bytes_end}", sprintf($this->req_id, $this->chunk - 1));
    }

    protected function getFileInfo()
    {
        $ch = curl_init($this->getUrl());
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Origin' => 'http://www.camwhores.tv'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 500);
        $data = curl_exec($ch);

        if (!curl_errno($ch)) {
            switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                case 200:
                    break;
                default:
                    throw new Exception("Invalid status code: {$http_code}");
            }
        }

        list($header, $body) = explode("\r\n\r\n", $data, 2);

        $headers = [];
        foreach (explode("\r\n", $header) as $_header) {
            list($name, $value) = explode(':', $_header, 2);
            $headers[trim($name)] = trim($value);
        }

        if (!isset($headers['X-Hola-Fullsize'])) {
            throw new Exception('Fullsize header not found');
        }
        $this->total_size = (int) $headers['X-Hola-Fullsize'];
        echo "Total File Size: {$this->total_size} (" . self::formatFilesize($this->total_size) . ')' . PHP_EOL;
        $this->total_chunks = ceil($this->total_size / $this->chunk_size);
        echo "Total Chunks: {$this->total_chunks}" . PHP_EOL;
    }

    protected function getChunk()
    {
        $url = $this->getUrl();
        echo "Chunk: {$this->chunk}" . PHP_EOL;
        echo $url . PHP_EOL;

        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_exec($this->curl);

        if (!curl_errno($this->curl)) {
            switch ($http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE)) {
                case 200:
                    break;
                default:
                    throw new Exception("Invalid status code: {$http_code}");
            }
        }

        if ($this->chunk < $this->total_chunks) {
            $this->chunk++;
            $this->getChunk();
        }
    }

    public function run()
    {
        $this->req_id = rand(1, 20) . '_%d';
        $this->file = fopen($this->output_file, 'w');
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, [
            'Origin' => 'http://www.camwhores.tv'
        ]);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($this->curl, CURLOPT_FILE, $this->file);
        try {
            $this->getFileInfo();
            $this->getChunk();
        } finally {
            curl_close($this->curl);
            fclose($this->file);
        }
    }
}

try {
    if (!isset($_SERVER['argv'][1])) {
        throw new Exception('No url provided');
    }
    if (!isset($_SERVER['argv'][2])) {
        throw new Exception('No output file provided');
    }
    if (preg_match('/^http(?:s)?:\/\/www\.camwhores\.tv\/videos\/([0-9]+)\/.*$/', $_SERVER['argv'][1], $match) !== 1) {
        throw new Exception('Unable to parse URL');
    }
    $id_2 = $match[1];
    $id_1 = substr($id_2, 0, strlen($id_2) - 3) . str_repeat('0', 3);
    $output_file = $_SERVER['argv'][2];
    $downloader = new Downloader($id_1, $id_2, $output_file);
    $downloader->run();
    echo 'Done' . PHP_EOL;
} catch (Exception $e) {
    echo $e;
}
