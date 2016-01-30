<?php
/**
 * Скрипт предназначен для Wordpress.
 * Скрипт исправляет блоки SyntaxHighlighter после импорта
 */
require('wp-load.php');

class FixAfterImportSH {
    private $_items;
    private $_tags = ['sourcecode','code'];

    /**
     * Проверка какой тег есть в коде поста
     * @param string $post_content
     * @param int $offset Позиция поиска
     * @return array
     */
    private function _checkTagInSource($post_content, $offset = 0){
        $result = ['tag' => '', 'pos' => false];

        foreach($this->_tags as $tag) {
            $test = '['.$tag ;
            $res = mb_strpos($post_content, $test , $offset, 'UTF-8');
            if($res !== false) {
                $result = ['tag' => $tag, 'pos' => $res];
                break;
            }
        }

        return $result;
    }

    /**
     * Поиск статей с SyntaxHighlighter
     */
    private function _searchSH() {
        $this->_items = [];
        $listPost = get_posts(['numberposts'=>-1]);

        /** @var WP_Post $itemPost */
        foreach($listPost as $itemPost) {
            $res = $this->_checkTagInSource($itemPost->post_content);
            if($res['pos'] !== false) {
                $this->_items[] = $itemPost;
            }
        }
    }

    /**
     * Разбиваем тектс на блоки. Блоки с SyntaxHighlighter помечены как sh
     * @param $content
     * @return array
     */
    private function _breakContent($content) {
        $result = [];

        $res = $this->_checkTagInSource($content);
        $offset = $res['pos'];

        while($offset !== false){
            $result[] = [
                'text' => mb_substr($content, 0, $offset, 'UTF-8'),
                'type' => 'txt'
            ];

            $content = mb_substr($content, $offset, null , 'UTF-8');

            // --- закрытие тега ---
            $endTag = '[/' . $res['tag'] . ']';
            $offsetEnd = mb_strpos($content, $endTag , null, 'UTF-8');
            if($offsetEnd !== false) {
                $result[] = [
                    'text' => mb_substr($content, 0, $offsetEnd , 'UTF-8'),
                    'type' => 'sh'
                ];

                $content = mb_substr($content, $offsetEnd, null, 'UTF-8');
            }

            $res = $this->_checkTagInSource($content);
            $offset = $res['pos'];
        }

        $result[] = [
            'text' => $content,
            'type' => 'txt'
        ];

        return $result;
    }

    function __construct() {
        $this->_searchSH();
    }

    public function echoItems() {
        echo '<h2>Посты с SyntaxHighlighter</h2>';

        /** @var WP_Post $itemPost */
        foreach($this->_items as $itemPost) {
            $url = get_permalink($itemPost);
            echo "<a href=\"{$url}\" target='_blank'> {$itemPost->post_title} </a><br>";
        }

        echo '<form><br><button type="submit" name="fix">Поправить</button><br></form>';
    }

    /**
     * Исправление блоков
     */
    public function fix(){
        /** @var WP_Post $itemPost */
        foreach($this->_items as $itemPost) {
            $content = $itemPost->post_content;

            $newText = '';
            $bloks = $this->_breakContent($content);
            foreach($bloks as $item) {

                // обычный текст
                if($item['type'] == 'txt') {
                    $newText .= $item['text'];
                    continue;
                }

                $text = $item['text'];

                /*
                $diffText = str_replace(
                    ['&amp;gt;', '&amp;lt;', '&amp;quot;', '&amp;amp;'],
                    ['&gt;', '&lt;', '&quot;', '&amp;'],
                    $text
                );
                */

                $diffText = str_replace(
                    ['&gt;', '&lt;', '&quot;', '&amp;'],
                    ['>', '<', '"', '&'],
                    $text
                );


                $newText .= $diffText;
            }

            wp_update_post([
                'ID' => $itemPost->ID,
                'post_content' => $newText
            ]);
        }
    }
}

$obj = new FixAfterImportSH();

if(isset($_GET['fix'])){
    $obj->fix();
}
else {
    $obj->echoItems();
}