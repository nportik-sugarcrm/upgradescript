<?php
class treeWalker {
    private $files, $path, $folders, $excludedFolders;
    private $tmpPath, $tmpTree;

    function __construct($basePath, $exclusion = array()) {
        $this->path = $basePath;
        $this->folders = array();
        $this->files = (object) [
            'unknown' => array()
        ];
        $this->excludedFolders = $exclusion;

        $this->tmpTree = array();
        array_push($this->tmpTree, $basePath);
    }

    public function getFilesList($ext, $list = false, $info = 'name') {
        if (isset($this->files->$ext)) {
            if ($list) { $this->_list($this->files->$ext, $info); }
            return $this->files->$ext;
        } else {
            $msg = 'There are no files matching the .' . $ext . ' extenstion.';
            if ($list) { echo $msg; } return $msg;
        }
    }

    public function getFolderList($list = false, $info = 'name') {
        if ($list) { $this->_list($this->folders, $info); }
        return $this->folders;
    }

    public function mapFiles() {
        while(count($this->tmpTree) > 0) {
            $treePath = array_values($this->tmpTree)[0];
            $this->_mapTreePath($treePath);
            $this->tmpTree = array_diff($this->tmpTree, [$this->tmpPath]);
        }
    }

    private function _list($structure, $info) {
        $br = '<br/>';
        echo $br . 'List: ' . $br;
        foreach($structure as $treeObject) {
            echo $treeObject->$info . $br;
        }
    }

    private function _mapTreePath($treePath) {
        $this->tmpPath = $treePath;
        $treeStructure = scandir($treePath);

        foreach($treeStructure as $filename) {
            $this->_detectStructure($filename);
        }
    }

    private function _detectStructure($fileName) {
        if (substr_count($fileName, '.') > 0) {
            if (strpos($fileName, '.') > 0) {
                $this->_registerFileByExtension($fileName);
            } else {
                $this->_registerFile($fileName, 'unknown');
            }
        } else {
            $path = $this->tmpPath . '/' . $fileName;
            if (is_dir($path)) {
                $this->_registerFolder($fileName, $path);
                $this->_includeFolderInIteration($fileName, $path);
            } else {
                $this->_registerFile($fileName, 'unknown');
            }
        }
    }

    private function _includeFolderInIteration($name, $path) {
        $include = array_search($name, $this->excludedFolders);
        if ($include === false) {
            array_push($this->tmpTree, $path);
        }
    }

    private function _registerFolder($folderName, $path) {
        array_push($this->folders, (object) [
            'path' => $path,
            'name' => $folderName
        ]);
    }

    private function _registerFileByExtension($fileName) {
        $a = explode('.', $fileName); $extension = end($a);
        if (!isset($this->files->$extension)) {
            $this->files->$extension = array();
        }
        $this->_registerFile($fileName, $extension);
    }

    private function _registerFile($fileName, $ext) {
        array_push($this->files->$ext, (object) [
            'name' => $fileName,
            'path' => $this->tmpPath . '/' . $fileName
        ]);
    }
}

?>