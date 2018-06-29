<?php
class fileManager {
    public function readFile($path) {
        $content = file_get_contents('../' . $path);
        print_r($content);
    }

    public function readFileByLines($path, $list = false) {
        $content_original = file($path);
        $content = [];
        foreach($content_original as $line) {
            if ($list) { echo '<br/>' . htmlentities($line); }
            $line = str_replace("\n", '', $line);
            $line = str_replace("\r\n", '', $line);
            $content[] = $line;
        }
        
        return $content;
    }

    public function write($path, $content) {
        $this->_ensureExistingFolder($path);
        $file = fopen($path, 'w');
        $this->_writeContent($file, $content);
        fclose($file);
    }

    private function _ensureExistingFolder($path) {
        $folderPathEnd = strrpos($path, '/');
        if ($folderPathEnd) {
            $folderPath = substr($path, 0, $folderPathEnd);
            if (!is_dir($folderPath)) {
                mkdir($folderPath, 0777);
            }
        }
    }

    private function _writeContent($file, $content) {
        forEach($content as $c) {
            fwrite($file, $c . "\n");
        }
    }
}
?>
