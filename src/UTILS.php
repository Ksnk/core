<?php
/**
 * Created by PhpStorm.
 * ножичек перочинный.
 * Date: 06.12.15
 * Time: 12:36
 */

namespace Ksnk\core;

use Autoload, ENGINE;
use Phar;
use PharFileInfo;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class UTILS
{

    /**
     * Чтобы не вспоминать мучительно. Кодировка даты совместимая с SQL,
     * а писать UTILS::SQLDATE
     */
    const SQLDATE = "Y-m-d H:i:s";

    /**
     * конвертировать в системную кодировку
     * @param $name - имя файла в кодировке utf
     * @param bool $to - провести обратную перекодировку, из системной
     * @return mixed|string
     */
    static function _2sys($name, $to = true)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $name = str_replace('\\', '/', $name);
            if ($to)
                return iconv('utf-8', 'cp1251', $name);
            else
                return iconv('cp1251', 'utf-8', $name);
        } else {
            return $name;
        }
    }

    /**
     * компресс чистого html, для вывода клиенту.  пакует javascript, стили и условные комментарии
     * @param $s
     * @return string|string[]|null
     */
    static function ob_html_compress($s){
        $curplaceloder=0; $placeholder=[];
        $_replace= function ($m) use (&$curplaceloder,&$placeholder)
        {
            $placeholder[$curplaceloder] = $m[2];
            return $m[1] . '@' . $curplaceloder++ . '@' . ($m[3]??'');
        };
        $_return = static function ($m) use (&$placeholder)
        {
            return $placeholder[$m[1]];
        };

        // выводим html для вставки в изображение строки с двойными кавычками.
        // $scripts
        // коррекция NL
        $s = str_replace(array("\r\n", "\r"), array("\n", "\n"), $s);
        // чистим шаблонные вставки
        // чистим скрипты
        $start = $curplaceloder;
        $s = preg_replace_callback('#(<script[^>]*>)(.*?)(</script[^>]*>)#is', $_replace, $s);
        /*
        $finph=$curplaceloder;
        for (; $start < $finph; $start++) {
            if(trim($placeholder[$start])==='') continue;
            // у нас есть текст JS. Ищем первую лексему
            $found=true;
            $pstart=0;$compress='';
            // удаляем комментарии
            while ($found){
                $found=preg_match('~(\'|"|//|/\*|/)~s',$placeholder[$start],$m, PREG_OFFSET_CAPTURE,$pstart);
                if(!$found){
                    $compress.=substr($placeholder[$start],$pstart);
                } else {
                    $compress.=substr($placeholder[$start],$pstart,$m[1][1]-$pstart);
                    $pstart=$m[1][1]+mb_strlen($m[1][0],'utf-8');
                    switch($m[1][0]){
                        case '//':
                            preg_match('~\n~s',$placeholder[$start],$m, PREG_OFFSET_CAPTURE,$pstart);
                            $pstart=$m[0][1]+mb_strlen($m[0][0],'utf-8');
                            break;
                        case '/*':
                            preg_match('~\*'.'/~s',$placeholder[$start],$m, PREG_OFFSET_CAPTURE,$pstart);
                            $pstart=$m[0][1]+mb_strlen($m[0][0],'utf-8');
                            break;
                        case '/':case '"':case "'":
                            $let=$m[1][0];
                            preg_match('~(?:\\'.$let.'|[^'.$let.'])*?'.$let.'~s',$placeholder[$start],$m, PREG_OFFSET_CAPTURE,$pstart);
                            $compress.=$_replace(['','',$let.$m[0][0],'']);
                            $pstart=$m[0][1]+mb_strlen($m[0][0],'utf-8');
                            break;
                    }
                }
            }
            $repl=[
  //              '/\)\s*?\n\s*?(? >\w)/m'=>');',
                '/\s+/'=>' ',
                '~\s*([\{\}])\s*~'=>'\1',
                '#\s*(\\\\n\s*)+#' => '\n'
            ];
            $placeholder[$start] = preg_replace_callback('#@(\d+)@#'
                , $_return,
                preg_replace(array_keys($repl),array_values($repl),$compress)
            );
        }
        */
        $start = $curplaceloder;
        $s = preg_replace_callback('#(<textarea[^>]*>)(.*?)(</textarea[^>]*>)#is', $_replace, $s);
        for (; $start < $curplaceloder; $start++) {
            $placeholder[$start] = preg_replace_callback('#@(\d+)@#'
                , $_return,$placeholder[$start]
            );
        }
        $start = $curplaceloder;
        $s = preg_replace_callback('#(<pre[^>]*>)(.*?)(</pre[^>]*>)#is', $_replace, $s);
        for (; $start < $curplaceloder; $start++) {
            $placeholder[$start] = preg_replace_callback('#@(\d+)@#'
                , $_return,$placeholder[$start]
            );
        }
        //стили
        $start = $curplaceloder;
        $s = preg_replace_callback('#(<style[^>]*>)(.*?)(</style[^>]*>)#is', $_replace, $s);
        for (; $start < $curplaceloder; $start++) {
            $repl=[
                '#/\*.*?\*/#s'=>'',
                '/\s\s+/'=>' ',
            ];
            $placeholder[$start] = preg_replace_callback('#@(\d+)@#', $_return,
                preg_replace(array_keys($repl),array_values($repl),$placeholder[$start])
            );
        }
        // условные комментарии
        $s = preg_replace_callback('#(<!--\[)(.*?)(]-->)#is', $_replace, $s);
        // пробелы
        $s = preg_replace(
            array('/<!--.*?-->/s',  '/\s+/'
            , '#\s*(<|</)(!doctype|html|body|div|br|script|style|form|option|dd|h1|h2|h3|h4|dt|dl|li|p|iframe)([^<]*>)\s*#is'),
            array('', ' ', '\1\2\3'),
            $s);
        return preg_replace_callback('#@(\d+)@#', $_return, $s);
    }

    /**
     * Вывести бинарную строку в printable виде
     * @param $str - строка
     * @return string
     */
    static function dumpPrintable($str)
    {
        $result = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $c = ord($str[$i]);
            if ($c >= 32 && $c < 127) { // chr(127) - nonprintable
                $result .= chr($c);
            } else {
                $result .= ($c < 16 ? '\x0' : '\x') . dechex($c);
            }
        }
        return $result;
    }

    static $months_rp = array('Января', 'Февраля', 'Марта', 'Апреля', 'Мая', 'Июня', 'Июля', 'Августа', 'Сентября', 'Октября', 'Ноября', 'Декабря');

    static function translit($text)
    {
        $ar_latin = array('a', 'b', 'v', 'g', 'd', 'e', 'jo', 'zh', 'z', 'i', 'j', 'k',
            'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'shh',
            '', 'y', '', 'je', 'ju', 'ja', 'je', 'i');
        $text = trim(str_replace(array('а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к',
            'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ',
            'ъ', 'ы', 'ь', 'э', 'ю', 'я', 'є', 'ї'),
            $ar_latin, $text));
        $text = trim(str_replace(array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К',
                'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ',
                'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', 'Є', 'Ї')
            , $ar_latin, $text));
        return $text;
    }

    /**
     * Проверка, что то, что прилетело имеет utf-8 кодирование
     * @param $string
     * @return false|int
     */
    static function detectUTF8($string)
    {
        return preg_match('%(?:
       [\xC2-\xDF][\x80-\xBF]        		# non-overlong 2-byte
       |\xE0[\xA0-\xBF][\x80-\xBF]          # excluding overlongs
       |[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}   # straight 3-byte
       |\xED[\x80-\x9F][\x80-\xBF]          # excluding surrogates
       |\xF0[\x90-\xBF][\x80-\xBF]{2}    	# planes 1-3
       |[\xF1-\xF3][\x80-\xBF]{3}           # planes 4-15
       |\xF4[\x80-\x8F][\x80-\xBF]{2}    	# plane 16
       )+%xs', $string);
    }

    /**
     * Класс-хелпер для отслеживания времени
     * @param bool $print
     * @param bool $save - не сохранять во внутренней переменной метку времени
     * @return mixed
     * @example
     * ...::mkt() // просто запомнить начало выполнения
     * ...::mkt('calculating part 1') - вывод времени, на выполнение, запомнить начало следующего интервала
     * ...::mkt(..., false) - не перезаписывать мремя, считать с точки замера
     */
    static function mkt($print = false, $save = true)
    {
        static $tm;
        $ttm = $tm;
        if ($save) $tm = microtime(1);
        if ($print) {
            printf(" %.03f sec spent%s (%s)\n", $tm - $ttm, is_string($print) ? ' for ' . $print : '', date(self::SQLDATE));
            return false;
        } else
            return microtime(1) - $ttm;
    }

    /**
     * scan phar files for files matches the mask
     * @param $phar
     * @param $mask
     * @return array
     */
    static function scanPharFile($phar, $mask)
    {
        if (!$phar instanceof Phar) {
            $phar = new Phar($phar);
        }
        $iterator = new RecursiveIteratorIterator($phar);
        $result = array();
        /** @var $f PharFileInfo */
        foreach ($iterator as $f) if (preg_match($mask, $f)) {
            // echo ' found '.$f."\n";
            $result[] = $f;
        }
        return $result;
    }

    /**
     * заменитель getallheaders для системы
     * @return array|false
     */
    static function getallheaders()
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        } else {
            $headers = array();
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        }
    }

    /**
     * Внутренняя функция для использования в uploadedFiles
     * @param $curr
     * @param $result
     * @param $path
     */
    private static function _relist(&$curr, &$result, $path)
    {
        foreach ($curr as $x => &$y) {
            if (!is_array($y))
                $result[] = $path . '|' . $x;
            else
                self::_relist($y, $result, $path . '|' . $x);
        }
    }

    /**
     * Выковырять загруженные файлы, прозрачно для всех возможных способов заливки
     * Обычный upload php мульти или не мульти, или заливка flashloader
     * @param string $name - имя поля формы. Если не указывать, будут все загруженные файлы
     * @return array
     */
    static function uploadedFiles($name='')
    {
        $uploaded = array();
        $paths = array();
        $FF=$_FILES;
        if(!empty($name)){
            if(isset($_FILES[$name])){
                $FF=[$name=>$_FILES[$name]];
            } else {
                $FF=[];
            }
        }
        if(!empty($FF))
        foreach ($FF as $x => $y) {
            if (!empty($_FILES[$x]['name'])) {
                self::_relist($_FILES[$x]['name'], $paths, $x . '|{{}}');
            }
        }
        foreach ($paths as $p) {
            $u = array(
                'name' => self::val($_FILES, str_replace('{{}}', 'name', $p), 'x'),
                'error' => self::val($_FILES, str_replace('{{}}', 'error', $p), 'x'),
                'tmp_name' => self::val($_FILES, str_replace('{{}}', 'tmp_name', $p), 'x'),
                'type' => self::val($_FILES, str_replace('{{}}', 'type', $p), 'x'),
                'size' => self::val($_FILES, str_replace('{{}}', 'size', $p), 'x'),
               // 'path' => $p
            );
            if(0===$u['error'] && empty($u['type']=='x')){
                $u['type']=mime_content_type ($u['tmp_name']);
            }
            if(0===$u['error'] && empty($u['size']=='x')){
                $u['size']=filesize($u['tmp_name']);
            }
            $uploaded[]=$u;
        }
        return $uploaded;
    }

    /**
     * парсинг конструкций .5k 1K и т.д.
     * @param $str
     * @return float|int
     */
    static function parseKMG($str){
        if(preg_match('/^(.*)(?:([кk])|([мm])|([гg]))$/iu',$str,$m)){
            if(!empty($m[2])) return 1024*$m[1];
            if(!empty($m[3])) return 1024*1024*$m[1];
            if(!empty($m[4])) return 1024*1024*1024*$m[1];
        }
        return 1*$str;
    }

    /**
     * convert simple DOS|LIKE mask with * and ? into regular expression
     * so
     *   * - all files - its'a a difference with the rest "select by mask"
     *   *.xml - all files with xlm extension
     *   *.jpg|*.jpeg|*.png -
     *   hello*world?.txt - helloworld1.txt,helloXXXworld2.txt, and so on
     *
     * @param $mask - simple mask
     * @param bool $last - mask ends with last simbol
     * @param bool $isfilemask - is this mask used to filter filenames?
     * @return string
     */
    static function masktoreg($mask, $last = true, $isfilemask = true)
    {
        if (!empty($mask) && $mask[0] == '/') return $mask; // это уже регулярка
        if ($isfilemask) {
            $star = '[^:/\\\\\\\\]';//
            $mask = explode('|', $mask);
        } else {
            $star = '.';//
            $mask = array($mask);
        }
        /* so create mask */
        $regs = array(
            '~\[~' => '@@0@@',
            '~\]~' => '@@1@@',
            '~[\\\\/]~' => '@@2@@',
            '/\*\*+/' => '@@3@@',
            '/\./' => '\.',
            '/\|/' => '\|',
            '/\*/' => $star . '*',
            '/\?/' => $star,
            '/#/' => '\#',
            '/@@3@@/' => '.*',
            '/@@2@@/' => '[\/\\\\\\\\]',
            '/@@1@@/' => '\]',
            '/@@0@@/' => '\[',
        );
        $r = array();
        foreach ($mask as $m)
            $r[] = preg_replace(
                    array_keys($regs), array_values($regs), $m
                ) . ($last ? '$' : '');
        return '#' . implode('|', $r) . '#iu';
    }

    /**
     * Выборка из массива-объекта
     * @param $rec - откуда
     * @param string $disp - чего
     * @param string $default - если нет, то вот это
     * @return mixed|string
     * @example ::val($_SESSION,'user|settings|email')
     */

    static function val($rec, $disp = '', $default = '')
    {
        if (empty($disp)){
            if(empty($rec)) return $default;
            return $rec;
        } ;
        $x = explode('|', $disp);
        $v = $rec;
        foreach ($x as $xx) {
            if (is_object($v)) {
                if (property_exists($v, $xx)) {
                    $v = $v->$xx;
                } else {
                    $v = $default;
                    break;
                }
            } elseif (is_array($v) && isset($v[$xx])) {
                $v = $v[$xx];
            } else {
                $v = $default;
                break;
            }
        }
        return $v;
    }

    /**
     * the future is here... sure...
     * Scaning direcory by mask + call callback then found
     * @param array|string $dirs
     * @param callable|null $callback
     * @return array
     */
    static function findFiles($dirs, $callback = null)
    {
        $result = array();
        if (!is_array($dirs)) $dirs = [$dirs];
        foreach ($dirs as $dir) {
            $mask = self::masktoreg(ltrim($dir,'/'));
            //ENGINE::debug($dir,$mask);
            $dd = preg_split('~[^/]*[\*\?]~', $dir, 2);
            if (false === $dd || count($dd) == 1) {
                //$ddir=$dir;
                $result[$dir] = 1;
            } else {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dd[0]?:'.'), RecursiveIteratorIterator::CHILD_FIRST
                );
                /** @var SplFileInfo $path */
                foreach ($iterator as $path) {
                    if ($path->isFile() && preg_match($mask, $path->getPathname())) {
                        $name = str_replace("\\", '/',
                            str_replace(dirname(__FILE__) . DIRECTORY_SEPARATOR, '', $path->getPathname()));
                        $result[$name] = 1;
                    }
                }
            }
        }
        if (!is_null($callback))
            foreach ($result as $name => $v) {
                if ($callback($name) === false) {
                    break;
                }
            }
        return $result;
    }

    /**
     * detect if running in CLI mode
     * @return bool
     */
    static function is_cli()
    {
        if (defined('STDIN')) {
            return true;
        }

        if (empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) {
            return true;
        }

        return false;
    }

    /**
     * умная склейка массивов в глубину, ветка вливается в существующую с сохранением предыдущих значений
     * @param $tomerge
     * @param $part
     * @return bool - вклеилось или нет.
     */
    static function array_merge_deep(&$tomerge, $part)
    {
        $result = false;
        // ассоциативный массив
        foreach ($part as $k => $v) {
            if (array_key_exists($k, $tomerge)) {
                if (is_array($tomerge[$k]) && is_array($v)) {
                    $result = self::array_merge_deep($tomerge[$k], $v) || $result;
                } elseif (is_null($v)) {
                    if (isset($tomerge[$k])) {
                        unset($tomerge[$k]);
                        $result = true;
                    }
                } else {
                    if (isset($tomerge[$k]) && $tomerge[$k] != $v) {
                        $tomerge[$k] = $v;
                        $result = true;
                    }
                }
            } elseif (!is_null($v)) {
                $tomerge[$k] = $v;
                $result = true;
            }
        }
        unset ($v);
        return $result;
    }

    /**
     * умная очистка значений в глубину
     * @param $tomerge
     * @param $part
     * @return bool
     */
    static function array_clear_deep(&$tomerge, &$part)
    {
        $result = false;
        foreach ($part as $k => &$v) {
            if (array_key_exists($k, $tomerge)) {
                if (is_array($tomerge[$k]) && is_array($v)) {
                    $result = self::array_clear_deep($tomerge[$k], $v) || $result;
                    if (count($tomerge[$k]) == 0) {
                        unset($tomerge[$k]);
                        $result = true;
                    }
                } else {
                    if (isset($tomerge[$k])) {
                        unset($tomerge[$k]);
                        $result = true;
                    }
                }
            }
        }
        unset ($v);
        return $result;
    }

    /**
     * @param array[string] $args
     */
    static function bundle($args=[]){
        $filename=[];$time=-1;$ext='';
        foreach($args as &$a){
            if (0===strpos($a,'http')) {
                $filename[]=$a;
                continue;
            }
            $a= Autoload::find($a);
            $pi = pathinfo($a);
            if ($pi['extension'] == 'js') {
                $ext='.js';
                 if (file_exists($pp = ($pi['dirname'] . '/' . $pi['filename'] . '.min.' . $pi['extension']))) {
                    $a=$pp;
                    $filename[]=$pi['basename'];
                    $time=max($time,filemtime($a));
                    continue;
                }
            } else if ($pi['extension'] == 'css') {
                $ext='.css';
                if (file_exists($pp = ($pi['dirname'] . '/' . $pi['filename'] . '.min.' . $pi['extension']))) {
                    $a=$pp;
                    $filename[]=$pi['basename'];
                    $time=max($time,filemtime($a));
                    continue;
                }
            } else {
                throw new \Exception('incorrect bundle arguments');
            }
            if(!file_exists($a)) continue;
            $filename[]=$pi['basename'];$time=max($time,filemtime($a));
        }
        unset($a);
       // 'TEMPLATE_PATH' => INDEX_DIR.'/'.$base.'/template'
        $base='/'.trim(ENGINE::option('page.base',''));
        $name=INDEX_DIR.$base.'/template/bundle'.crc32(implode(' ',$filename)).$ext;
        if(file_exists($name)){
            if (filemtime($name)>=$time)
                return ENGINE::link(realpath($name), 'file2url') . '?' . filemtime($name);
        }
        $h=fopen($name,'a+');
        foreach($args as $p){
            if (0===strpos($p,'http')) {
                $file2 = file_get_contents($p);
            } elseif(!file_exists($p)) {
                continue;
            } else {
                $file2 = file_get_contents($p);
            }
            fwrite($h, $file2.PHP_EOL);
        }
        fclose($h);
        return ENGINE::link(realpath($name), 'file2url') . '?' . filemtime($name);
    }

    /**
     * Для использования в шаблоне link('-',...,'&'). Пример - шаблоны администратора
     * '-',[a,b,c,d] - выкинуть из url все параметры a=,b=,c=,d=
     * ...,'&')~'a=2&b=3...' - к линку добавляются параметры
     * url - поиск файла и генерация ссылки на него с индексом - timestamp от файла.
     * Для js и css делается попытка найти минимизированные версии файлов
     * @param string $p1
     * @param string $p2
     * @param string $p3
     * @return string|null
     */
    static function url($p1 = '', $p2 = '', $p3 = '')
    {
        $link = ENGINE::link('');
        if ($p1 == '-') {
            if (!is_array($p2)) $p2 = array($p2);
            $repl = [];
            foreach ($p2 as $x) {
                $repl['/[\?&]' . preg_quote($x) . '=[^\&]*/'] = '';
            }
            $link = preg_replace(array_keys($repl), array_values($repl), $link);
            // чистка артефактов url
            $repl = [
                '/\?&+/' => '?', // xxx?&yyy=1 --- >xxx?yyy=1
                '/&&+/' => '&', // xxx?a=1&&yyy=1 --- >xxx?a=1&yyy=1
                '/[\?&]+$/' => '', // xxx?a=1&& --- >xxx?a=1
            ];
            $link = preg_replace(array_keys($repl), array_values($repl), $link);
        } else {
            while(!empty($p1) && $p= Autoload::find($p1)) {
                $pi = pathinfo($p);
                if ($pi['extension'] == 'js') {
                    if (file_exists($pmin = ($pi['dirname'] . '/' . $pi['filename'] . '.min.' . $pi['extension']))) {
                        $link = ENGINE::link(realpath($pmin), 'file2url') . '?' . filemtime($p);
                        break;
                    }
                } else if ($pi['extension'] == 'css') {
                    if (file_exists($pmin = ($pi['dirname'] . '/' . $pi['filename'] . '.min.' . $pi['extension']))) {
                        $link = ENGINE::link(realpath($pmin), 'file2url') . '?' . filemtime($p);
                        break;
                    }
                }
                $link = ENGINE::link(realpath($p), 'file2url') . '?' . filemtime($p);
                break;
            }
        }
        if ($p3 == '&') {
            if (false === strpos($link, '?')) {
                $link .= '?';
            } else {
                $link .= '&';
            }
        }

        return $link;
    }

    /**
     * конверсия рускоязыких URL туда-сюда (туда)
     * @param $url
     * @return false|string
     */
    static function idn_to_utf8($url){
        if(function_exists('idn_to_utf8'))
            return idn_to_utf8($url);
        else {
            return IDN::decodeIDN($url);
        }
    }

    /**
     * конверсия рускоязыких URL туда-сюда (сюда)
     * @param $url
     * @return false|string
     */
    static function idn_to_ascii($url){
        if(function_exists('idn_to_ascii'))
            return idn_to_ascii($url);
        else
            return $url;
    }
}