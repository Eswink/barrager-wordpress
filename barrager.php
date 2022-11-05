<?php defined('ABSPATH') or exit;
/*
Plugin Name: 评论弹幕
Plugin URI: https://www.esw.ink
Description: 通过弹幕的方式全站展示WordPress评论，修复一系列问题，使用REST API
Version: 1.2
Author: Eswink
Author URI: https://www.esw.ink
 */

//输入执行JS
add_action('wp_enqueue_scripts', 'barrager_script');
function barrager_script()
{
    $options = get_option('barrager');
    foreach ($options['position'] as $opt) {
        if (($opt == 'home' && is_home()) ||
            ($opt == 'category' && is_category()) ||
            ($opt == 'page' && is_page()) ||
            ($opt == 'single' && is_singular())
        ) {
            if (is_singular() || is_page()) {
                global $post;
                $postid = $post->ID;
                if (get_comments_number() == 0) { //判断有无评论
                    $postid = '0';
                }
            } else { $postid = '0';}
            $home = home_url("/");
            $mode = $options['mode'];
            wp_enqueue_style('barrager', plugins_url('css/barrager.css', __FILE__), '', time(), 'all');
            wp_enqueue_script('myjs', plugins_url('js/jquery-3.6.1.min.js', __FILE__), '', time(), true); //js加载到底部
            wp_enqueue_script('barrager', plugins_url('js/jquery.barrager.min.js', __FILE__), '', time(), true); //js加载到底部

            //wp_enqueue_script(    'barrager',    plugins_url('js/jquery.barrager.js',__FILE__),    '',    time(),    true    );//js加载到底部
            wp_localize_script('barrager', 'barrager',
                array(
                    "url" => $home,
                    "id" => $postid,
                    "mode" => $mode,
                )
            );
        }
    }
}

//保存数据
$option = get_option('barrager'); //获取选项
if ($option == '' || isset($_POST['option_reset'])) {
    //设置默认数据
    $option = array(
        'count' => '20',
        'position' => array(
            'home', 'single',
        ),
        'mode' => '2',
    );
    update_option('barrager', $option); //更新选项
}
if (isset($_POST['option_save'])) {
    if ($_POST['count'] == 0) {$count = 1;} else { $count = $_POST['count'];} //防止为0输出全部
    //处理数据
    $option = array(
        'count' => $count,
        'position' => $_POST['position'],
        'mode' => $_POST['mode'],
    );
    update_option('barrager', $option); //更新选项
}
if (isset($_POST['option_reset'])) {
    //delete_option('barrager');//删除数据
}

//添加发布框
//add_action( 'wp_footer', 'boj_front_page_meta_description' );
function boj_front_page_meta_description()
{
/* 得到站点描述 */
    $description = esc_attr(get_bloginfo('description'));
/* 如果 description 设置了，显示 meta 元素 */
    if (!empty($description)) {
        echo $description;
    }

}

//插件设置菜单
function barrager_menu()
{
    add_submenu_page('options-general.php', '评论弹幕设置', '评论弹幕', 'manage_options', 'barrager_menu', 'barrager_options', '');
}
function barrager_options()
{
    if (isset($_POST['option_save'])) {echo '<div class="updated notice is-dismissible"><strong><p>更新成功！</p></strong></div>';}
    if (isset($_POST['option_reset'])) {echo '<div class="error notice is-dismissible"><strong><p>还原成功！</p></strong></div>';}
    $options = get_option('barrager');
    echo '<div class="wrap">';
    echo '<h2>评论弹幕</h2>';
    echo '<form method="post">';
    echo wp_nonce_field('update-options');
    echo '<table class="form-table">';

    echo '<tr valign="top">';
    echo '<th scope="row">弹幕数量</th>';
    echo '<td><input type="number" name="count" value="' . $options['count'] . '" /></td>';
    echo '</tr>';

    echo '<tr valign="top">';
    echo '<th scope="row">展现位置</th>';
    echo '<td>';
    $position = array('home', 'category', 'page', 'single');
    $position_cn = array('首页', '分类页', '页面', '文章页');
    foreach ($position as $key => $opt) {
        echo '<lable style="margin-right: 20px;">';
        echo '<input name="position[]" type="checkbox" ';
        echo 'value="' . $opt . '"';
        foreach ($options['position'] as $opts) {
            if ($opts == $opt) {echo ' checked';}
        }
        echo '/>';
        echo $position_cn[$key];
        echo '</lable>';
    }
    echo '</td>';
    echo '</tr>';

    echo '<tr valign="top">';
    echo '<th scope="row">运行模式</th>';
    echo '<td><select name="mode">';
    $mode = array('1', '2');
    $mode_cn = array('实时模式', '单次模式');
    foreach ($mode as $key => $opt) {
        echo '<option value="' . $opt . '"';
        if ($options['mode'] == $opt) {echo ' selected="selected"';}
        echo '>';
        echo $mode_cn[$key];
        echo '</option>';
    }
    echo '</select></td>';
    echo '</tr>';

    echo '</table>';
    echo '<p class="submit">';
    echo '<input type="submit" name="option_save" id="submit" class="button-primary" value="保存更改" />';
    echo ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    echo '<input type="submit" name="option_reset" id="submit" class="button" value="还原默认" />';
    echo '</p>';
    echo '</form>';
    echo '<p><strong>使用说明</strong><br>弹幕数量：确定显示最近的的多少条评论弹幕(越多对服务器压力越大);<br>展现位置：确定弹幕在网站哪些地方显示；<br>运行模式：实时模式=通过数据库获取随机展现；单次模式=单次获取数据并且依次展示</p>';
    echo '</div>';
}
add_action('admin_menu', 'barrager_menu');

function dmd_rest_register_route()
{
    register_rest_route('esw/v1', 'get-barrager/(?P<barrager>[\d]+)/(?P<id>[\d]+)', [
        'methods' => 'GET',
        'callback' => 'dmd_rest_postlist_callback',
    ]);
}
add_action('rest_api_init', 'dmd_rest_register_route');
function dmd_rest_postlist_callback($request)
{
    $barrager = $request->get_param('barrager');
    $id = $request->get_param('id');
    return get_postlist($barrager, $id);
}
function get_postlist($barrager, $id)
{

    $options = get_option('barrager');
    //判断id
    if ($id == null || !isset($id)) {$id = 0;}
    ;
    $args = array(
        'status' => 'approve', //评论的状态：批准=approve
        'type' => 'comment', //评论格式，防止pingpack
        'post__not_in' => $id, //排除当前文章的评论
    );
    $comments = get_comments($args);
    $show_comments = $options['count']; //评论数量
    $i = 1;
    $barrages = array();
    foreach ($comments as $rc_comment) {
        if ($rc_comment->comment_author_email != get_bloginfo('admin_email')) {

            $info = convert_smilies($rc_comment->comment_content);
            $avatar = get_avatar_url($rc_comment->comment_author_email, array('size' => 32));
            $avatar = str_replace(array("www.gravatar.com", "0.gravatar.com", "1.gravatar.com", "2.gravatar.com"), "cn.gravatar.com", $avatar);
            $href = get_permalink($rc_comment->comment_post_ID);
            $barrages[] =
            array(
                'info' => $info,
                'img' => $avatar,
                'href' => $href,
            );
            if ($i == $show_comments) {
                break;
            }
            //评论数量达到退出遍历
            $i++;
        } // End if
    } //End foreach

    //输出模式
    $data = [];
    $statement = 200;
    if (!empty($barrages)) {
        if ($barrager == 1) {
            $data = $barrages[array_rand($barrages)];
        } elseif ($barrager == 2) {
            $data = $barrages;
        }
        $statement = 200;
    } else {
        $data = array(
          'info' => "没有更多的评论啦！",
          'img' => "https://q2.qlogo.cn/headimg_dl?dst_uin=10000&spec=100",
          'href' => "https://www.esw.ink",
      );
    }

    $response = new WP_REST_Response($data, $statement);
    return $response;

}