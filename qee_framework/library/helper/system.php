<?php
/**
 * This file belongs to system.php
 * 
 * @link    www.blogdaren.com
 * @author  manon<lgh_2002@163.com>
 * @version 
 * @modify  2016-12-16 14:36:03
 */

class Helper_System
{
    /**
     * 占位符处理, 将占位符顺次处理成映射值 - 目前仅支持问号占位符 - 其他占位符请自行扩展
     *
     * @原始数据形如:  
     * - $subject = "k1 = ? and k2 = ? and k3 = ?";         //标准表达式
     * - $subject = "k1 =    ?    and k2 =   ?  and k3 = ?";//支持多个空格的野表达式
     * - $subject = "k1 = ??? and k2 = ?? and k3 = ?";      //支持多个连续问号的的野表达式
     * - $subject = "k1 = ???and k2 = ??and k3 = ?";        //支持问号和运算符粘连的野表达式 
     * - $subject = "k1 in(?) and k2 = ?";                  //支持IN表达式
     * - $replace = array(1,2,3);                           //支持完整映射
     * - $replace = array(1,2);                             //支持局部映射
     * - $replace = array(1,2,array(3));                    //支持标准内嵌数组
     * - $replace = array(1,2,array(array(3)));             //支持多重内嵌数组: 却不影响数据
     *
     * @期望输出结果:  
     * - $replace = "k1 = 1 and k2 = 2 and k3 = 3"
     * - $replace = "k1 = 1 and k2 = 2"
     * - $replace = "k1 = 1 and k2 = 2 and k3 = [3]"
     * - $replace = "k1 in(1,2,3)"
     * - $replace = "k1 in(1,2) and k2 = 3"
     * - ............
     *
     * @额外案例:
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 in(?) and k2 > ?", array("v1","v2"), "v3");
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 in(?) and k2 > ?", array(array("v1","v2"), "v3"));
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 in(?) and k2 > ?", array(array("v1","v2"), "v3"), 8);
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 in(?) and k2 > ?", array(array(array("v1","v2"))), "v3");
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 in(?) and k2 > ?", array(array(array(array("v1","v2")))), "v3");
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 in(?) and k2 = ?", 1, 2, 3);
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 = ? and k2 = ?", 1, 2, 3);
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 = ? and k2 = ? and k3 > ?", 1, 2, 3);
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 = ? and k2 = ?", array(1, 2), 3);
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 in(?) and k2 > ? and k3 in(?)", array(1, 2), 3, array(4,5));
     * - $output = Helper_System::doReplaceForPlaceHolder("k1 in(?) and k2 > ? and k3 in(?)", array(array(array(1,2), 3, array(4,5))));
     *
     * @return  string
     */
    static public function doReplaceForPlaceHolder()
    {
        $subject = '';
        $args = func_get_args();

        if(empty($args)) return $subject;
        $subject = array_shift($args);
        if(empty($args)) return $subject;
        $replace = $args;

        //暂时只支持问号占位符: 请自行扩展占位符外部参数
        $place_holder = "?";
        $allow_place_holder = array('?');
        if(!is_string($place_holder) || !in_array($place_holder, $allow_place_holder)) return $subject;

        //$replace 必须统一为数组进行处理
        !is_array($replace) && $replace = array($replace);

        //克隆一个subject副本,最终期望输出形如 $output = "k1 = 1 and k2 = 2 and k3 = 3";
        $clone_subject = $subject;

        //重要:过滤掉重复的占位符
        $subject = preg_replace("/\?+/is", $place_holder, $subject); 
        preg_match_all("/\?/is", $subject,  $matches);
        $matches = !isset($matches[0]) ? array() : $matches[0];

        //占位符替换: 如果提供的映射值数量不够,则多余的占位符保持原貌,即不予处理
        $output = '';
        $in_keywords = array('in', 'IN', 'In', 'iN');
        if(!empty($matches) && is_array($matches))
        {
            foreach($matches as $k => $v)
            {
                //最后一次循环确保截至末尾
                $segement = count($matches) == $k + 1 ? substr($subject, 0) : substr($subject, 0, strpos($subject, "?") + 1);

                //判断表达式是否含有IN关键字
                $find_in_keyword = false;
                foreach($in_keywords as $k1 => $v1)
                {
                    if(strpos($segement, $v1) !== false)
                    {
                        $find_in_keyword = true;
                        break;
                    }
                }

                //如果不包含关键词IN
                if($find_in_keyword === false && self::getArrayDepth($replace) >= 2) 
                {
                    is_array($replace[0]) && $replace = $replace[0];
                }

                if(!isset($replace[1]) && self::getArrayDepth($replace) >= 2) 
                {
                    $replace = $replace[0];
                }

                $subject = str_replace($segement, '', $subject);

                //重大修复!!!
                if(!isset($replace[$k])) 
                {
                    $k == 0 && $output = '';
                    $k <> 0 && $output .= substr($segement, 0, 1);
                    continue;  
                }
                //重大修复!!!

                if(is_array($replace[$k])) 
                {
                    $replace[$k] = self::getArrayDepth($replace[$k]) == 1 
                                 ?  join("," , $replace[$k]) : json_encode($replace[$k]);
                }

                $replace[$k] = str_replace(array("'", '"', '[', ']'), "", $replace[$k]);
                $replace[$k] =  "'" . str_replace(",", "','", $replace[$k]) . "'";
                $replace[$k] = " " . $replace[$k] . " ";

                $output .= str_replace("?", $replace[$k], $segement);
            }
        }

        //重要:过滤掉多余的空格
        $output = preg_replace("/\s+/is", " ", $output);

        //若$output为空,则输出原始字符串
        empty($output) && $output = $clone_subject;

        return $output;
    }

    /**
     * 给数组降级
     *
     * @param  int  $input  原始数组
     * @param  int  $level  降到哪一级
     *
     * @return array        降级后的数组 
     */
    static function degradeArray($input = array(), $level = 1)
    {
        !self::checkIsInt($level) && $level = 1;

        $depth = self::getArrayDepth($input);

        if($depth <= $level) return $input;

        $diff = abs($depth - $level);

        for($i = 0; $i < $diff; $i++)
        {
            if(!is_array($input[0])) return $input;
            $input = array_shift($input);
        }

        return $input;
    }

    /**
     * 检验一个数据项是否为正整数
     *
     * @param  string  $input
     *
     * @return boolean
     */
    static public function checkIsInt($input)
    {
        return preg_match('/^[1-9][0-9]*$/is', $input, $matches);
    }


    /**
     * 计算数组的维数即深度 
     *
     * @param  int  $array
     *
     * @return int 
     */
    static function getArrayDepth($array) 
    {
        $max_depth = 1;

        if(!is_array($array)) return $max_depth;

        foreach($array as $value) 
        {
            if(is_array($value)) 
            {
                $depth = self::getArrayDepth($value) + 1;

                if($depth > $max_depth) 
                {
                    $max_depth = $depth;
                }   
            }   
        }   

        return $max_depth;
    }






}












