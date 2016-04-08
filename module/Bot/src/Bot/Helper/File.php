<?php
namespace Bot\Helper;

class File
{
    public static function isValidImg($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        /**
         * It's better to check not only content type but also a length to be sure that we downloading a real image...
         */
        return $info['http_code'] === 200 && in_array($info['content_type'], [
            'image/jpeg',
            'image/png'
        ]);
    }

    public static function downloadImage($url, $destination)
    {
        $fp = fopen($destination, 'w+');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $result = curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        return $result;
    }
}