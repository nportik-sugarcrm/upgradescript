<?php
class pathFixer {
    private $tree;
    private $helperTags;

    /**
     * It will initiate the process for removing contexts created by
     * conditional statements. A file's content will be read into an
     * array, then processed line by line. As the last step, the array
     * will be written and saved into the same file.
     * @param hbs The path to a hbs file.
     */
    public function run($hbs, $filemanager) {
        $this->tree = array();
        $this->helperTags = array();

        $newContent = array();
        $manager = new fileManager();
        $oldContent = $manager->readFileByLines($hbs->path);

        forEach($oldContent as $line) {
            $tags = $this->collectTags($line);
            array_push($this->helperTags, $tags);
        }

        forEach($oldContent as $i=>$line) {
            $newLine = $this->getUpdatedLine($i, $line);
            array_push($newContent, $newLine);
        }

        $manager->write($hbs->path, $newContent);
    }

    /***************************************** */
    /********** Create a map of helpers ****** */
    /***************************************** */

    /**
     * Looks through a line and composes a list of tags to be considered.
     * The given line is broken up into small fragments, each holding a tag.
     * From each fragment tag information is collected.
     * @param line A line from a file's content.
     * @return tags Information about tags found in the given line.
     */
    private function collectTags($line) {
        $tags = array();
        $fragment = $line;
        $progressMeter = 0;
        $tagStartPos = $this->getTagStartPos($line);

        while(is_int($tagStartPos)) {
            $tag = $this->getTag($fragment, $tagStartPos, $progressMeter);
            $progressMeter = $tag->endpos;
            if ($this->isContextChanger($tag)) {
                array_push($tags, $tag);
            }

            $fragment = substr($line, $tag->endpos);
            $tagStartPos = $this->getTagStartPos($fragment);
        }

        return $tags;
    }

    /**
     * Attempts to find a tag (opening or closing),
     * but it must return the tag which is encountered sooner.
     * If there aren't any tags in the given line, returns false.
     * @param str A chunk from the content.
     * @return position The index of the tag based on the given string.
     */
    private function getTagStartPos($str) {
        $tagEnd = '{{/';
        $tagStart = '{{#';
        $tagEndPos = strpos($str, $tagEnd);
        $tagStartPos = strpos($str, $tagStart);
        return $this->_jsmin($tagStartPos, $tagEndPos);
    }

    /**
     * Read the tag from the given position and collect any information needed later.
     * @param str Unprocessed fragment of a line.
     * @param tagStartPos The index from where the tag starts in the fragment.
     * @param progressMeter The length of the already processed fragment.
     * @return tag Information about the tag (name, starting and ending indexes).
     */
    private function getTag($str, $tagStartPos, $progressMeter) {
        $tag = new StdClass();
        $tag->index = $progressMeter + $tagStartPos;
        $tag->name = $this->getTagName($str, $tagStartPos);
        $tag->endpos = $progressMeter + $this->getTagEndPos($str, $tagStartPos);
        return $tag;
    }

    /**
     * Read the name of the tag from the given string.
     * @param str Fragment of a line.
     * @param tagStartPos The index from where the tag starts in the fragment.
     * @return tagName The name of the tag with it's marker (# or /).
     */
    private function getTagName($str, $tagStartPos) {
        $tagStart = substr($str, $tagStartPos);
        $tagType = substr($tagStart, 2, 1);
        if ($tagType === '#') {
            $nameEndPos = strpos($tagStart, ' ');
        } else {
            $nameEndPos = strpos($tagStart, '}}');
        }
        $tagName = substr($str, $tagStartPos + 2, $nameEndPos -2);
        return $tagName;
    }

    /**
     * Finds the index where the tag is closed in the given string.
     * @param str Fragment of a line.
     * @param tagStartPos The index from where the tag starts in the fragment.
     * @return endBracesPos The index where the first tag ends.
     */
    private function getTagEndPos($str, $tagStartPos) {
        $tagStart = substr($str, $tagStartPos);
        $tagType = substr($tagStart, 2, 1);
        $endBracesPos = $tagStartPos + strpos($tagStart, '}}') + 2;
        return $endBracesPos;
    }

    /**
     * Checks if the given tag is a tag which creates a new context.
     * To put it simple: checks if it is an 'if', 'unless' or 'each' tag;
     * it doesn't matter if it is closing or opening tag.
     * @param tag Tag information object.
     * @return 
     */
    private function isContextChanger($tag) {
        $isIf = strpos($tag->name, 'if');
        $isEach = strpos($tag->name, 'each');
        $isUnless = strpos($tag->name, 'unless');
        return $isIf || $isUnless || $isEach;
    }

    /***************************************** */
    /*********** Replace ../ occurences ****** */
    /***************************************** */

    /**
     * In case the line has tags in it, it needs to be fragmented, then
     * the individual fragments processed against the helper tree.
     * In case there are no tags in the line, it will trigger an update
     * process without cutting the text into smaller pieces.
     * @param i Line number.
     * @param line A line from the original content.
     * @return newline The updated line.
     */
    private function getUpdatedLine($i, $line) {
        if (!$this->helperTags[$i]) {
            $newline = $this->updateFragment($line);
        } else {
            $newline = $this->updateLineByFragments($line, $this->helperTags[$i]);
        }
        return $newline;
    }

    /**
     * Cuts the content's line into smaller fragments
     * based on the conditional statements end-tag.
     * Each fragment will be forwarded for path replacement,
     * in the same time the helper tree is updated to be able
     * to identify into which block the fragment belongs.
     * @param line A line from the content.
     * @param tags A list of tags found in the current line.
     * @return newline The line with updated paths.
     */
    private function updateLineByFragments($line, $tags) {
        $j = 0;
        $newline = '';
        $progress = 0;

        while(isset($tags[$j])) {
            $tag = $tags[$j];
            $fragment = substr($line, $progress, $tag->endpos - $progress);
            $newline .= $this->updateFragment($fragment);

            $j++;
            $progress = $tag->endpos;
            $this->updateHelperTree($tag);
        }

        $fragment = substr($line, $progress, $tag->endpos);
        $newline .= $fragment;

        return $newline;
    }

    /**
     * Decomposes a fragment into segments,
     * sends the path segment to the replacer.
     * @param fragment The fragment is a piece from a line.
     * @return updatedFragment The fragment with updated paths.
     */
    private function updateFragment($fragment) {
        $dds = '../';
        $updatedFragment = '';

        $progress = 0;
        $segment = $fragment;
        $pathPos = strpos($segment, $dds);
        $hasPath = is_int($pathPos);

        while ($hasPath) {
            $cleanSegment = substr($segment, 0, $pathPos);
            $pathSegment = $this->getPathSegment($segment, $pathPos);
            $progress += strlen($cleanSegment) + strlen($pathSegment);
            $updatedPath = $this->updatePath($pathSegment);
            $updatedFragment = $updatedFragment . $cleanSegment . $updatedPath;

            $segment = substr($fragment, $progress);
            $pathPos = strpos($segment, $dds);
            $hasPath = is_int($pathPos);
        }

        $updatedFragment .= $segment;
        return $updatedFragment;
    }

    /**
     * Extracts the path string from a text.
     * @param segment A segment of a fragment of a line of a content.
     * @param pathPos Starting index of the path.
     * @return pathSegment The path string from the given string.
     */
    private function getPathSegment($segment, $pathPos) {
        $i = 1;
        $dds = '../';
        $pathSegment = '';
        $path = substr($segment, $pathPos, strlen($dds));
        while($path === $dds) {
            $pathSegment .= $dds;
            $path = substr($segment, $pathPos + $i * strlen($dds), strlen($dds));
            $i++;
        }
        return $pathSegment;
    }

    /**
     * Checks the path against the helper tree and if finds
     * that a context was created due to a conditional statement
     * removes a "dot dot slash" from the path. 
     * Contexts created by the each tag are left untouched.
     * @param path A path string. ex.: ../../../
     */
    private function updatePath($path) {
        $dds = '../';
        $pathDepth = strlen($path) / strlen($dds);
        $reverseTree = array_reverse($this->tree);

        for ($i = 0; $i < $pathDepth; $i++) {
            if (isset($reverseTree[$i]) && $reverseTree[$i] !== '#each') {
                $path = substr($path, strlen($dds));
            }
        }

        return $path;
    }

    /**
     * Manage the helper hierarchy tree.
     * This tree will be appended to each tag,
     * so we could easily check what parrents the tag is having.
     * @param tag Tag information object.
     */
    private function updateHelperTree($tag) {
        if (strpos($tag->name, '#') === 0) {
            array_push($this->tree, $tag->name);
        } else {
            array_pop($this->tree);
        }
    }

    /***************************************** */
    /************* Utility ******************* */
    /***************************************** */

    /**
     * Method "min" returns false/nothing if one of the value is not a number.
     * I need the functionality of js Math.min, which disregards false values.
     * @param a First number.
     * @param b Second number.
     * @return min The smaller of the 2 numbers.
     */
    private function _jsmin($a, $b) {
        if (is_int($a) && is_int($b)) {
            $min = min($a, $b);
        } else if (is_int($a)) {
            $min = $a;
        } else if (is_int($b)) {
            $min = $b;
        } else {
            $min = false;
        }
        return $min;
    }
}
?>
