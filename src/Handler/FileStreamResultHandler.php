<?PHP
declare(strict_types=1);
namespace PSwag\Handler;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use PSwag\Model\FileStreamResult;

class FileStreamResultHandler
{
    public function handle(FileStreamResult $result, ResponseInterface $response): ResponseInterface {
        if (!file_exists($result->filePath)) {
            throw new Exception("File does not exist");
        }

        $start = 0;
        $size  = filesize($result->filePath);
        $end = $size - 1;
        $response = $response->withAddedHeader('Accept-Ranges', 'bytes');//'0-'.$end);

        $defaultLength = 1024*1024*8;

        if (isset($_SERVER['HTTP_RANGE']))
        {
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) return $response->withStatus(416, 'Requested Range Not Satisfiable');

            list($r_start, $r_end) = explode('-', trim($range), 2);
            if (($r_start!='' && !is_numeric($r_start)) || ($r_end!='' && !is_numeric($r_end))) return $response->withStatus(416, 'Requested Range Not Satisfiable');

            $c_start = $r_start=='' ? 0 : intval($r_start);
            $c_end = $r_end=='' ? min($end, $c_start+$defaultLength-1) : intval($r_end);

            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size)  return $response->withStatus(416, 'Requested Range Not Satisfiable');

            $start = $c_start;
            $end = $c_end;

            if ($start!=0 || $end!=$size-1) $response = $response->withStatus(206, 'Partial Content');
            $response = $response->withHeader('Content-Range', 'bytes '.$start.'-'.$end.'/'.$size);
        }
        
        if ($result->cacheMaxAge!=null) $response = $response->withHeader('Cache-control', 'max-age='.$result->cacheMaxAge)->withHeader('Expires', gmdate(DATE_RFC1123,time()+$result->cacheMaxAge));
        $mimeType = mime_content_type($result->filePath);
        $fileName = $result->fileName;
        if ($fileName==null) {
            $parts = explode('/', $result->filePath);
            $fileName = $parts[count($parts)-1];
        }
        return $response
            ->withBody(new PartialStream($result->filePath, $start, $end))
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Length', 1 + $end - $start)
            ->withHeader('Content-disposition', 'attachment;filename="'.str_replace('"', '_', $fileName).'"')
            ->withHeader('Last-Modified', gmdate(DATE_RFC1123, @filemtime($result->filePath)));
    }
}

class PartialStream implements StreamInterface {
    private $stream;
    private int $start;
    private int $end;

    function __construct(string $absoluteFilePath, int $start, int $end) {
        $this->stream = fopen($absoluteFilePath, 'rb');
        $this->start = $start;
        $this->end = $end; // end is expected to be the index of the last char
        if ($start!=0) fseek($this->stream, $start);
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     */
    public function __toString(): string {
        $fileContent = "";
        $buffer = 102400;
        fseek($this->stream, $this->start);
        $i = $this->start;
        while(!feof($this->stream) && $i <= $this->end) {
            $bytesToRead = $buffer;
            if(($i+$bytesToRead) > $this->end) {
                $bytesToRead = $this->end - $i + 1;
            }
            $$fileContent .= fread($this->stream, $bytesToRead);
            $i += $bytesToRead;
        }
        return $fileContent;
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close(): void {
        fclose($this->stream);
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach() {
        $oldResource = $this->stream;
        $this->stream = null;
        return $oldResource;
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize(): ?int {
        return 1 + $this->end - $this->start;
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell(): int {
        return ftell($this->stream)-$this->start;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof(): bool {
        return ftell($this->stream)>$this->end || feof($this->stream);
    }

    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable(): bool {
        return true;
    }

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws \RuntimeException on failure.
     */
    public function seek(int $offset, int $whence = SEEK_SET): void {
        fseek($this->stream, $offset+$this->start, $whence);
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @see seek()
     * @link http://www.php.net/manual/en/function.fseek.php
     * @throws \RuntimeException on failure.
     */
    public function rewind(): void {
        fseek($this->stream, $this->start);
    }

    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable(): bool {
        return false;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     * @throws \RuntimeException on failure.
     */
    public function write(string $string): int {
        throw new \RuntimeException("Stream is not writable");
    }

    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable(): bool {
        return true;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read(int $length): string {
        flush(); // workaround to force PHP to output larger content
        return fread($this->stream, $length);
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return string
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents(): string {
        return fread($this->stream, $this->getUnreadBytes());
    }

    private function getUnreadBytes(): int {
        $cursor = ftell($this->stream);
        return 1+$this->end-$cursor;
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string|null $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata(?string $key = null) {
        if (!$this->stream) {
            return null;
        }

        $meta = $this->createMetadata();
        if (!$key) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }

    private function createMetadata() {
        return array (
            "timed_out" => false,
            "blocked" => false,
            "eof" => $this->eof(),
            "unread_bytes" => $this->getUnreadBytes(),
            "stream_type" => "PartialStream",
            "wrapper_type" => "PartialStream",
            "wrapper_data" => null,
            "mode" => "",
            "seekable" => $this->isSeekable(),
            "uri" => ""
        );
    }
}