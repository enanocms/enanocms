<?php
/*
Plugin Name: EnanoPress
Plugin URI: http://enano.homelinux.org/EnanoPress
Description: Adds WordPress-like blogging functionality to the site. The blog can be viewed on the page Special:Blog, and posts can be written with Special:WriteBlogPost.
Author: Dan Fuhry
Version: 1.0
Author URI: http://enano.homelinux.org/
*/

global $db, $session, $paths, $template, $plugins; // Common objects

$plugins->attachHook('base_classes_initted', '
  $paths->add_page(Array(
    \'name\'=>\'Site Blog\',
    \'urlname\'=>\'Blog\',
    \'namespace\'=>\'Special\',
    \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
    ));
  $paths->add_page(Array(
    \'name\'=>\'Write blog post\',
    \'urlname\'=>\'WriteBlogPost\',
    \'namespace\'=>\'Special\',
    \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
    ));
  $paths->addAdminNode(\'Plugin configuration\', \'EnanoPress settings\', \'EnanoPress\');
  ');

$plugins->attachHook('compile_template', 'global $template; $template->tpl_bool[\'in_blog\'] = false;');
$plugins->attachHook('paths_init_before', 'global $paths; $paths->create_namespace("Blog", "BlogPost:");');
$plugins->attachHook('page_not_found', 'return EnanoPress_BlogNamespaceHandler();');
$plugins->attachHook('page_type_string_set', 'global $paths, $template; if($paths->namespace == "Blog") $template->namespace_string = "blog post";');

define('BLOG_POST_PUBLISHED', 1);
define('BLOG_POST_DRAFT', 0);
define('BLOG_POSTS_PER_PAGE', 20);

function EnanoPress_BlogNamespaceHandler()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $pid = intval($paths->cpage['urlname_nons']);
  if($pid == 0) return null;
  $q = $db->sql_query('SELECT post_id, post_title, post_content, time, author FROM '.table_prefix.'blog WHERE status='.BLOG_POST_PUBLISHED.' AND post_id='.$pid.';');
  if(!$q) $db->_die('');
  if($db->numrows() < 1) return null;
  $row = $db->fetchrow($q);
  $paths->cpage['name'] = $row['post_title'];
  $template->header();
  echo EnanoPress_FormatBlogPost($row['post_title'], RenderMan::render($row['post_content']), $row['time'], $row['author'], 0, $row['post_id']);
  echo EnanoPress_Separator();
  $sub = ( isset ($_GET['sub']) ) ? $_GET['sub'] : false;
  $act = ( isset ($_GET['action']) ) ? $_GET['action'] : false;
  $id = ( isset ($_GET['id']) ) ? intval($_GET['id']) : -1;
  $comments = EnanoPress_GetComments($id);
  echo $comments;
  $template->footer();
  return true;
}

function page_Special_Blog()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if(!getConfig('blog_table_version'))
  {
    $q = $db->sql_query('CREATE TABLE '.table_prefix.'blog ( post_id mediumint(8) NOT NULL auto_increment, post_title text, post_content text, time int(12), status tinyint(1) NOT NULL DEFAULT 0, author varchar(63) NOT NULL, num_comments mediumint(8) NOT NULL DEFAULT 0, PRIMARY KEY ( post_id ) );');
    if(!$q) $db->_die('The blog table could not be created');
    setConfig('blog_table_version', '1');
  }
  if($n = getConfig('blog_name')) $paths->cpage['name'] = $n;
  if(!defined('ENANO_TEMPLATE_LOADED')) 
    $template->init_vars();
  $template->tpl_bool['in_blog'] = true;
  $template->header();
    if($s = $paths->getParam(0))
    {
      if($s == 'archive')
      {
        $y = (int)$paths->getParam(1);
        $m = (int)$paths->getParam(2);
        $d = (int)$paths->getParam(3);
        $t = $paths->getParam(4);
        if(!$y || !$m || !$d || !$t)
        {
          echo '<p>Invalid permalink syntax</p>';
          $template->footer();
          return false;
        }
        $t = $db->escape(str_replace(Array('-', '_'), Array('_', '_'), $t)); // It's impossible to reconstruct the title from the URL, so let MySQL do it for us using wildcards
        // Determine the valid UNIX timestamp values
        $lower_limit = mktime(0, 0, 0, $m, $d, $y);
        // EnanoPress will officially stop working on February 29, 2052. To extend the date, add more leap years here.
        $leapyears = Array(2000,2004,2008,2012,2016,2020,2024,2028,2032,2040,2044,2048);
        // add one to the day
        // 30 days hath September, April, June, and November, all the rest have 31, except el enano, February :-P
        if    (in_array($m, Array(4, 6, 9, 11)) && $d == 30) $m++;
        elseif(in_array($m, Array(1, 3, 5, 7, 8, 10, 12)) && $d == 31) $m++;
        elseif($m == 2 && in_array($y, $leapyears)  && $d == 29) $m++;
        elseif($m == 2 && !in_array($y, $leapyears) && $d == 28) $m++;
        else $d++;
        $upper_limit = mktime(0, 0, 0, $m, $d, $y);
        $q = $db->sql_query('SELECT b.post_id, b.post_title, b.post_content, b.time, COUNT(c.comment_id) AS num_comments, b.author FROM '.table_prefix.'blog AS b LEFT JOIN '.table_prefix.'comments AS c ON (c.page_id=b.post_id AND c.namespace=\'Blog\' AND c.approved=1) WHERE b.status='.BLOG_POST_PUBLISHED.' AND b.post_title LIKE \''.$t.'\' AND b.time >= '.$lower_limit.' AND b.time <= '.$upper_limit.' GROUP BY b.post_id ORDER BY b.time DESC;');
        if(!$q)
        {
          echo $db->get_error();
          $template->footer();
          return;
        }
        if($db->numrows() < 1)
        {
          // Try it with no date specifiation
          $q = $db->sql_query('SELECT b.post_id, b.post_title, b.post_content, b.time, COUNT(c.comment_id) AS num_comments, b.author FROM '.table_prefix.'blog AS b LEFT JOIN '.table_prefix.'comments AS c ON (c.page_id=b.post_id AND c.namespace=\'Blog\' AND c.approved=1) WHERE b.status='.BLOG_POST_PUBLISHED.' AND b.post_title LIKE \''.$t.'\' GROUP BY b.post_id ORDER BY b.time DESC;');
          if(!$q)
          {
            echo $db->get_error();
            $template->footer();
            return;
          }
          if($db->numrows() < 1)
          {
            echo '<p>No posts matching that permalink could be found.</p>';
            $template->footer();
            return;
          }
        }
        $row = $db->fetchrow();
        echo EnanoPress_FormatBlogPost($row['post_title'], RenderMan::render($row['post_content']), $row['time'], $row['author'], (int)$row['num_comments'], (int)$row['post_id']);
        echo EnanoPress_Separator();
        $sub = ( isset ($_GET['sub']) ) ? $_GET['sub'] : false;
        $act = ( isset ($_GET['action']) ) ? $_GET['action'] : false;
        $id = ( isset ($_GET['id']) ) ? intval($_GET['id']) : -1;
        $comments = EnanoPress_GetComments((int)$row['post_id']);
        if(is_array($comments))
        {
          $comments = EnanoPress_FormatComments($comments);
          echo $comments;
        }
        $template->footer();
        return;
      }
      else
      {
        $start = intval($s);
      }
    }
    else $start = 0;
    $end = $start + BLOG_POSTS_PER_PAGE + 1;
    $q = $db->sql_query('SELECT b.post_id, b.post_title, b.post_content, b.time, b.author, COUNT(c.comment_id) AS num_comments FROM '.table_prefix.'blog AS b LEFT JOIN '.table_prefix.'comments AS c ON (c.page_id=b.post_id AND c.namespace=\'Blog\' AND c.approved=1) WHERE b.status='.BLOG_POST_PUBLISHED.' GROUP BY b.post_id ORDER BY b.time DESC LIMIT '.$start.','. $end .';');
    if(!$q) { echo $db->get_error('The blog data could not be selected'); $template->footer(); return false; }
    $numrows = $db->numrows();
    if($numrows == BLOG_POSTS_PER_PAGE+1)
    {
      $nextpage = true;
      $numrows = BLOG_POSTS_PER_PAGE;
    }
    if($numrows < 1)
    {
      echo '<p>No posts yet! <a href="'.makeUrlNS('Special', 'WriteBlogPost').'">Write a post...</a></p>';
    }
    else
    {
      $i = 0;
      while($row = $db->fetchrow())
      {
        $i++;
        if($i == BLOG_POSTS_PER_PAGE+1) break;
        echo EnanoPress_FormatBlogPost($row['post_title'], RenderMan::render($row['post_content']), $row['time'], $row['author'], (int)$row['num_comments'], (int)$row['post_id']);
        if($i < $numrows) echo EnanoPress_Separator();
      }
      if($session->user_level >= USER_LEVEL_MOD) echo '<h2>More actions</h2><p><a href="'.makeUrlNS('Special', 'WriteBlogPost').'">Write a post...</a></p>';
    }
  $template->footer();
}

function page_Special_WriteBlogPost()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if($session->user_level < USER_LEVEL_MOD) die_friendly('Access denied', '<p>You are not authorized to post blog messages.</p>');
  $errors = Array();
  $template->header();
  $editing = false;
  if(isset($_POST['__save'])) $status = BLOG_POST_DRAFT;
  if(isset($_POST['__publish'])) $status = BLOG_POST_PUBLISHED;
  if(isset($_POST['__save']) || isset($_POST['__publish']))
  {
    $text = RenderMan::preprocess_text($_POST['content'], false, true);
    $title = $db->escape(htmlspecialchars($_POST['title']));
    $author = $db->escape($session->username);
    $time = time();
    if($text == '') $errors[] = 'You must enter a post.';
    if($title == '') $errors[] = 'You must enter a title for your post.';
    if(sizeof($errors) < 1)
    {
      if(isset($_POST['edit_id']) && preg_match('#^([0-9]+)$#', $_POST['edit_id']))
      {
        $q = $db->sql_query('UPDATE '.table_prefix."blog SET post_title='{$title}',post_content='{$text}',time={$time},author='{$author}',status=".$status." WHERE post_id={$_POST['edit_id']};");
      }
      else
      {
        $q = $db->sql_query('INSERT INTO '.table_prefix."blog(post_title,post_content,time,author,status) VALUES('{$title}', '{$text}', {$time}, '{$author}', ".$status.");");
      }
      if(!$q)
      {
        echo $db->get_error();
        $template->footer();
        return;
      }
      $q = $db->sql_query('SELECT post_id FROM '.table_prefix.'blog WHERE time='.$time.' ORDER BY post_id DESC;');
      if(!$q) { echo $db->get_error(); $template->footer(); return false; }
      if($db->numrows() > 0)
      {
        $row = $db->fetchrow();
        $editing = $row['post_id'];
      }
      switch($status):
        case BLOG_POST_DRAFT:
          echo '<div class="info-box">Your post has been saved; however it will not appear on the main blog page until it is published.</div>';
          break;
        case BLOG_POST_PUBLISHED:
          echo '<div class="info-box">Your post has been published to the main blog page.</div>';
          break;
      endswitch;
    }
    
    $text =& $_POST['content'];
    $title =& $_POST['title'];
  }
  elseif(isset($_POST['__delete']) && isset($_POST['del_confirm']))
  {
    $pid = intval($_POST['edit_id']);
    if($pid > 0)
    {
      $q = $db->sql_query('DELETE FROM '.table_prefix.'blog WHERE post_id='.$pid.';');
      if(!$q)
      {
        echo $db->get_error();
        $template->footer();
        return;
      }
      else
        echo '<div class="info-box">Your post has been deleted.</div>';
    }
    $text  = '';
    $title = '';
    $editing = false;
  }
  elseif($t = $paths->getParam(0))
  {
    $id = intval($t);
    if($t == 0) die('SQL injection attempt');
    $q = $db->sql_query('SELECT post_title,post_content FROM '.table_prefix.'blog WHERE post_id='.$t.';');
    if(!$q) { echo $db->get_error(); $template->footer(); return false; }
    if($db->numrows() > 0)
    {
      $row = $db->fetchrow();
      $text =& $row['post_content'];
      $title =& $row['post_title'];
      $editing = $t;
    }
    else
    {
      $text  = '';
      $title = '';
    }
  }
  elseif(isset($_POST['__preview']))
  {
    $text = RenderMan::preprocess_text($_POST['content'], false, false);
    $text = RenderMan::render($text);
    ob_start();
    eval('?>'.$text);
    $text = ob_get_contents();
    ob_end_clean();
    echo '<div class="warning-box"><b>Reminder:</b><br />This is only a preview - your changes to this post will not be saved until you click Save Draft or Save and Publish below.</div>'
        . PageUtils::scrollBox(EnanoPress_FormatBlogPost($_POST['title'], $text, time(), $session->username, 0, false));
    $text =& $_POST['content'];
    $title = $_POST['title'];
  }
  else
  {
    $text  = '';
    $title = '';
  }
  if(sizeof($errors) > 0)
  {
    echo '<div class="error-box"><b>The following errors were encountered:</b><br />' .  implode('<br />', $errors) . '</div>';
  }
  $q = $db->sql_query('SELECT post_id, post_title FROM '.table_prefix.'blog WHERE status='.BLOG_POST_DRAFT.' ORDER BY post_title ASC;');
  if(!$q) { echo $db->get_error('The blog data could not be selected'); $template->footer(); return false; }
  $n = $db->numrows();
  if($n > 0)
  {
    echo '<br /><div class="mdg-comment"><b>Your drafts: </b>';
    $posts = Array();
    while($r = $db->fetchrow())
    {
      $posts[$r['post_id']] = $r['post_title'];
    }
    $i=0;
    foreach($posts as $id => $t)
    {
      $i++;
      echo '<a href="'.makeUrlNS('Special', 'WriteBlogPost/'.$id).'">'.$t.'</a>';
      if($i < $n) echo ' &#0187; ';
    }
    echo '</div>';
  }
  $idthing = ( $editing ) ? '<input type="hidden" name="edit_id" value="'.$editing.'" />' : '';
  $delbtn  = ( $editing ) ? '  <input onclick="return confirm(\'Are you REALLY sure you want to delete this post?\')" type="submit" name="__delete" value="Delete this post" style="color: red; font-weight: bold;" /> <label><input type="checkbox" name="del_confirm" /> I\'m sure</label>' : '';
  $textarea = $template->tinymce_textarea('content', $text);
  echo '<form action="'.makeUrl($paths->page).'" method="post">'
       . '<p>Post title:<br /><input type="text" name="title" size="60" style="width: 98%;" value="'.htmlspecialchars($title).'" /><br /><br />Post:<br />'
       . $textarea
       . '<p>The following information will be added to your post:</p><ul><li>Date and time: '.date('F d, Y h:i a').'</li><li>Username: '.$session->username.'</li></ul>'
       . '<p><input type="submit" name="__preview" value="Show preview" title="Allows you to preview your blog post before it is saved or posted" />  <input title="Saves the post but prevents it from being shown on the main blog page" type="submit" name="__save" value="Save Draft" />  <input title="Saves the blog post and shows it on the main blog page" type="submit" name="__publish" value="Save and Publish" />'
       . $delbtn
       . '</p>'
       . $idthing
       . '</form>';
  $template->footer();
}

/**
 * Convert a blog post to HTML
 * @param string $title the name of the blog post
 * @param string $text the content, needs to be HTML formatted as no renderer is called
 * @param int $time UNIX timestamp for the time of the post
 * @param string $author [user]name of the person who wrote the post
 * @param int $num_comments The number of comments attached to the post
 * @param int $post_id The numerical ID of the post
 * @return string
 */

function EnanoPress_FormatBlogPost($title, $text, $time, $author, $num_comments = 0, $post_id)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  static $cached_template = false;
  if(!$cached_template)
  {
    if(file_exists(ENANO_ROOT.'/themes/'.$session->theme.'/blogpost.tpl'))
      $cached_template = file_get_contents(ENANO_ROOT.'/themes/'.$session->theme.'/blogpost.tpl', 'r');
    if(!$cached_template)
      $cached_template = <<<TPLCODE
      <div>
        <div style="border-bottom: 1px solid #AAAAAA;">
          <p style="float: right; background-color: #F0F0F0; margin: 3px 10px 0 0; padding: 8px 3px; width: 55px; text-align: center;">{D} {j} {M} {Y}</p>
          <div style="margin-bottom: 16px;"><h3 style="margin-bottom: 0;"><a href="{PERMALINK}" rel="bookmark" title="Permanent link to this post">{TITLE}</a></h3>Posted by <a href="{AUTHOR_LINK}" {AUTHOR_USERPAGE_CLASS}>{AUTHOR}</a><br /><a href="{COMMENT_LINK}">{COMMENT_LINK_TEXT}</a><!-- BEGIN can_edit -->  |  <a href="{EDIT_LINK}">edit this post</a><!-- END can_edit --></div>
        </div>
        <div>
        {CONTENT}
        </div>
      </div>
TPLCODE;
  }
  $parser = $template->makeParserText($cached_template);
  $datechars = 'dDjlSwzWFmMntLYyaABGhHisIOTZrU'; // A list of valid metacharacters for date()
  $datechars = enano_str_split($datechars);
  $datevals = Array();
  foreach($datechars as $d)
  {
    $datevals[$d] = date($d, $time);
  }
  unset($datechars);
  $parser->assign_vars($datevals);
  $parser->assign_bool(Array(
    'can_edit'=> ( $session->user_level >= USER_LEVEL_MOD ),
    ));
  $permalink = makeUrlNS('Special', 'Blog/archive/'.date('Y', $time).'/'.date('m', $time).'/'.date('d', $time).'/'.enanopress_sanitize_title($title));
  $commentlink = $permalink . '#post-comments';
  if($num_comments == 0) $ctext = 'No comments';
  elseif($num_comments == 1) $ctext = '1 comment';
  else $ctext = $num_comments . ' comments';
  $edit_link = ( is_int($post_id) ) ? makeUrlNS('Special', 'WriteBlogPost/'.$post_id) : '#" onclick="return false;';
  $parser->assign_vars(Array(
      'TITLE' => $title,
      'PERMALINK' => $permalink,
      'AUTHOR' => $author,
      'AUTHOR_LINK' => makeUrlNS('User', $author),
      'AUTHOR_USERPAGE_CLASS' => ( isset($paths->pages[$paths->nslist['User'].$author]) ) ? '' : ' class="wikilink-nonexistent" ',
      'COMMENT_LINK' => $commentlink,
      'COMMENT_LINK_TEXT' => $ctext,
      'CONTENT' => $text,
      'EDIT_LINK' => $edit_link,
    ));
  return $parser->run();
}

/**
 * Draws a separator for use between blog posts - searches for the appropriate template file
 * @return string
 */

function EnanoPress_Separator()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  static $cached_template = false;
  if(!$cached_template)
  {
    if(file_exists(ENANO_ROOT.'/themes/'.$session->theme.'/blogseparator.tpl'))
      $cached_template = file_get_contents(ENANO_ROOT.'/themes/'.$session->theme.'/blogseparator.tpl');
    if(!$cached_template)
      $cached_template = <<<TPLCODE
    <div style="border-bottom: 1px dashed #666666; margin: 15px auto; width: 200px;"></div>
TPLCODE;
  }
  $parser = $template->makeParserText($cached_template);
  return $parser->run();
}

/**
 * Make a blog post title acceptable for URLs
 * @param string $text the input text
 * @return string
 */

function enanopress_sanitize_title($text)
{
  $text = strtolower(str_replace(' ', '_', $text));
  $badchars = '/*+-,.?!@#$%^&*|{}[];:\'"`~';
  $badchars = enano_str_split($badchars);
  $dash = Array();
  foreach($badchars as $i => $b) $dash[] = "-";
  $text = str_replace($badchars, $dash, $text);
  return $text;
}

/**
 * Fetch comments for a post
 * @param int $post_id The numerical ID of the post to get comments for
 * @return array A hierarchial array - numbered keys, each key is a subarray with keys "name", "subject", "text", "time", and "comment_id" with time being a UNIX timestamp
 */
 
function EnanoPress_GetComments($post_id)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if(!is_int($post_id)) return false;
  
  if(isset($_GET['sub']))
  {
    $e = $db->sql_query('SELECT comment_id,name,subject,comment_data,user_id FROM '.table_prefix.'comments WHERE comment_id='.intval($_REQUEST['id']).';');
    if($e)
    {
      $comment = $db->fetchrow();
      $auth_edit = ( ( intval($comment['user_id']) == $session->user_id && $session->user_logged_in ) || $session->user_level >= USER_LEVEL_MOD );
      if($auth_edit)
      {
        switch($_GET['sub'])
        {
          case 'editcomment':
            if(!isset($_GET['id']) || ( isset($_GET['id']) && !preg_match('#^([0-9]+)$#', $_GET['id']) )) { echo '<p>Invalid comment ID</p>'; break; }
            $row =& $comment;
            echo '<h3>Edit comment</h3><form action="'.makeUrl($paths->fullpage, 'sub=savecomment').'" method="post">';
            echo "<br /><div class='mdg-comment' style='padding: 0;'><table border='0' width='100%' cellspacing='1' cellpadding='4'>
                    <tr><td class='row1'>Subject:</td><td class='row1'><input type='text' name='subj' value='{$row['subject']}' /></td></tr>
                    <tr><td class='row2'>Comment:</td><td class='row2'><textarea rows='10' cols='40' style='width: 98%;' name='text'>{$row['comment_data']}</textarea></td></tr>
                    <tr><td class='row1' colspan='2' class='row1' style='text-align: center;'><input type='hidden' name='id' value='{$row['comment_id']}' /><input type='submit' value='Save Changes' /></td></tr>
                  </table></div>";
            echo '</form>';
            return false;
            break;
          case 'savecomment':
            if(empty($_POST['subj']) || empty($_POST['text'])) { echo '<p>Invalid request</p>'; break; }
            $r = PageUtils::savecomment_neater((string)$post_id, 'Blog', $_POST['subj'], $_POST['text'], (int)$_POST['id']);
            if($r != 'good') { echo "<pre>$r</pre>"; return false; }
            break;
          case 'deletecomment':
            if(isset($_GET['id']))
            {
              $q = 'DELETE FROM '.table_prefix.'comments WHERE comment_id='.intval($_GET['id']).' LIMIT 1;';
              $e=$db->sql_query($q);
              if(!$e)
              {
                echo 'Error during query: '.mysql_error().'<br /><br />Query:<br />'.$q;
                return false;
              }
              $e=$db->sql_query('UPDATE '.table_prefix.'blog SET num_comments=num_comments-1 WHERE post_id='.$post_id.';');
              if(!$e)
              {
                echo 'Error during query: '.mysql_error().'<br /><br />Query:<br />'.$q;
                return false;
              }
            }
            break;
          case 'admin':
            if(isset($_GET['action']) && $session->user_level >= USER_LEVEL_MOD) // Nip hacking attempts in the bud
            {
              switch($_GET['action']) {
              case "delete":
                if(isset($_GET['id']))
                {
                  $q = 'DELETE FROM '.table_prefix.'comments WHERE comment_id='.intval($_GET['id']).' LIMIT 1;';
                  $e=$db->sql_query($q);
                  if(!$e)
                  {
                    echo 'Error during query: '.mysql_error().'<br /><br />Query:<br />'.$q;
                    return false;
                  }
                  $e=$db->sql_query('UPDATE '.table_prefix.'blog SET num_comments=num_comments-1 WHERE post_id='.$post_id.';');
                  if(!$e)
                  {
                    echo 'Error during query: '.mysql_error().'<br /><br />Query:<br />'.$q;
                    return false;
                  }
                }
                break;
              case "approve":
                if(isset($_GET['id']))
                {
                  $where = 'comment_id='.intval($_GET['id']);
                  $q = 'SELECT approved FROM '.table_prefix.'comments WHERE '.$where.' LIMIT 1;';
                  $e = $db->sql_query($q);
                  if(!$e) die('alert(unesape(\''.rawurlencode('Error selecting approval status: '.mysql_error().'\n\nQuery:\n'.$q).'\'));');
                  $r = $db->fetchrow();
                  $a = ( $r['approved'] ) ? '0' : '1';
                  $q = 'UPDATE '.table_prefix.'comments SET approved='.$a.' WHERE '.$where.';';
                  $e=$db->sql_query($q);
                  if(!$e)
                  {
                    echo 'Error during query: '.mysql_error().'<br /><br />Query:<br />'.$q;
                    return false;
                  }
                  if($a == '1')
                  {
                    $q = 'UPDATE '.table_prefix.'blog SET num_comments=num_comments+1 WHERE post_id='.$post_id.';';
                  }
                  else
                  {
                    $q = 'UPDATE '.table_prefix.'blog SET num_comments=num_comments-1 WHERE post_id='.$post_id.';';
                  }
                  $e=$db->sql_query($q);
                  if(!$e)
                  {
                    echo 'Error during query: '.mysql_error().'<br /><br />Query:<br />'.$q;
                    return false;
                  }
                }
                break;
              }
            }
            break;
        }
      }
      else
      {
        echo '<div class="error-box">You are not authorized to perform this action.</div>';
      }
    }
  }
  
  if(isset($_POST['__doPostBack']))
  {
    if(getConfig('comments_need_login') == '2' && !$session->user_logged_in) echo('Access denied to post comments: you need to be logged in first.');
    else
    {
      $cb=false;
      if(getConfig('comments_need_login') == '1' && !$session->user_logged_in)
      {
        if(!isset($_POST['captcha_input']) || !isset($_POST['captcha_id']))
        {
          echo('BUG: PageUtils::addcomment: no CAPTCHA data passed to method');
          $cb=true;
        }
        else
        {
          $result = $session->get_captcha($_POST['captcha_id']);
          if($_POST['captcha_input'] != $result) { $cb=true; echo('The confirmation code you entered was incorrect.'); }
        }
      }
      if(!$cb)
      {
        $text = RenderMan::preprocess_text($_POST['text']);
        $name = $session->user_logged_in ? RenderMan::preprocess_text($session->username) : RenderMan::preprocess_text($_POST['name']);
        $subj = RenderMan::preprocess_text($_POST['subj']);
        if(getConfig('approve_comments')=='1') $appr = '0'; else $appr = '1';
        $q = 'INSERT INTO '.table_prefix.'comments(page_id,namespace,subject,comment_data,name,user_id,approved,time) VALUES(\''.$post_id.'\',\'Blog\',\''.$subj.'\',\''.$text.'\',\''.$name.'\','.$session->user_id.','.$appr.','.time().')';
        $e = $db->sql_query($q);
        if(!$e) echo 'Error inserting comment data: '.mysql_error().'<br /><br />Query:<br />'.$q;
        else
        {
          echo '<div class="info-box">Your comment has been posted.</div>';
          if(getConfig('approve_comments')=='1')
          {
            $e=$db->sql_query('UPDATE '.table_prefix.'blog SET num_comments=num_comments+1 WHERE post_id='.$post_id.';');
            if(!$e)
            {
              echo 'Error during query: '.mysql_error().'<br /><br />Query:<br />'.$q;
              return false;
            }
          }
        }
      }
    }
  }
  
  $apprv_clause = ( $session->user_level >= USER_LEVEL_MOD ) ? '' : 'AND approved=1';
  
  $q = $db->sql_query('SELECT c.comment_id,c.subject,c.comment_data,c.name,c.time,c.approved,c.time,u.signature,u.user_level,u.user_id FROM '.table_prefix.'comments AS c
                          LEFT JOIN '.table_prefix.'users AS u
                            ON u.user_id=c.user_id
                          WHERE page_id='.$post_id.'
                            AND namespace=\'Blog\'
                            '.$apprv_clause.'
                          ORDER BY time DESC;');
  if(!$q)
  {
    echo $db->get_error();
    return false;
  }
  $posts = Array();
  while($row = $db->fetchrow())
  {
    $row['text'] =& $row['comment_data'];
    $posts[] = $row;
  }
  return $posts;
}

/**
 * Formats a comments array from EnanoPress_GetComments() as HTML
 * @param array $comments The array of fetched comments
 * @return string
 */

function EnanoPress_FormatComments($comments)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  ob_start();
  $tpl = $template->makeParser('comment.tpl');
  
  $seed = substr(md5(microtime() . mt_rand()), 0, 12);
  
  ?>
  <script type="text/javascript">
    function toggleCommentForm()
    {
      document.getElementById('commentform_<?php echo $seed; ?>').style.display = 'block';
      document.getElementById('commentlink_<?php echo $seed; ?>').style.display = 'none';
    }
  </script>
  <?php
  
  echo "<h3 id='post-comments'>Post comments</h3>";
  if ( count($comments) < 1 )
  {
    $commentlink = ( getConfig('comments_need_login') == '2' && !$session->user_logged_in ) ? '<a href="'.makeUrl('Special:Login/'.$paths->fullpage).'">Log in to post a comment...</a>' : '<a href="'.makeUrl($paths->fullpage, 'act=postcomment', true).'" id="commentlink_'.$seed.'" onclick="toggleCommentForm(); return false;">Leave a comment...</a>' ;
    echo '<p>There are no comments on this post. Yours could be the first! '.$commentlink.'</p>';
  }
  $i = -1;
  
  foreach($comments as $comment)
  {
    $auth_edit = ( ( intval($comment['user_id']) == $session->user_id && $session->user_logged_in ) || $session->user_level >= USER_LEVEL_MOD );
    $auth_mod  = ( $session->user_level >= USER_LEVEL_MOD );
    
    // Comment ID (used in the Javascript apps)
    $strings['ID'] = (string)$i;
    
    // Determine the name, and whether to link to the user page or not
    $name = '';
    if($comment['user_id'] > 0) $name .= '<a href="'.makeUrlNS('User', str_replace(' ', '_', $comment['name'])).'">';
    $name .= $comment['name'];
    if($comment['user_id'] > 0) $name .= '</a>';
    $strings['NAME'] = $name; unset($name);
    
    // Subject
    $s = $comment['subject'];
    if(!$comment['approved']) $s .= ' <span style="color: #D84308">(Unapproved)</span>';
    $strings['SUBJECT'] = $s;
    
    // Date and time
    $strings['DATETIME'] = date('F d, Y h:i a', $comment['time']);
    
    // User level
    switch($comment['user_level'])
    {
      default:
      case USER_LEVEL_GUEST:
        $l = 'Guest';
        break;
      case USER_LEVEL_MEMBER:
        $l = 'Member';
        break;
      case USER_LEVEL_MOD:
        $l = 'Moderator';
        break;
      case USER_LEVEL_ADMIN:
        $l = 'Administrator';
        break;
    }
    $strings['USER_LEVEL'] = $l; unset($l);
    
    // The actual comment data
    $strings['DATA'] = RenderMan::render($comment['text']);
    
    // Edit link
    $strings['EDIT_LINK'] = '<a href="'.makeUrl($paths->fullpage, 'sub=editcomment&amp;id='.$comment['comment_id']).'" id="editbtn_'.$i.'">edit</a>';
    
    // Delete link
    $strings['DELETE_LINK'] = '<a href="'.makeUrl($paths->fullpage, 'sub=deletecomment&amp;id='.$comment['comment_id']).'">delete</a>';
    
    // Send PM link
    $strings['SEND_PM_LINK'] = ( $session->user_logged_in && $comment['user_id'] > 0 ) ? '<a href="'.makeUrlNS('Special', 'PrivateMessages/Compose/To/'.$comment['name']).'">Send private message</a>' : '';
    
    // Add Buddy link
    $strings['ADD_BUDDY_LINK'] = ( $session->user_logged_in && $comment['user_id'] > 0 ) ? '<a href="'.makeUrlNS('Special', 'PrivateMessages/FriendList/Add/'.$comment['name']).'">Add Buddy</a>' : '';
    
    // Mod links
    $applink = '';
    $applink .= '<a href="'.makeUrl($paths->fullpage, 'sub=admin&amp;action=approve&amp;id='.$comment['comment_id']).'" id="mdgApproveLink'.$i.'">';
    if($comment['approved']) $applink .= 'Unapprove';
    else $applink .= 'Approve';
    $applink .= '</a>';
    $strings['MOD_APPROVE_LINK'] = $applink;
    unset($applink);
    $strings['MOD_DELETE_LINK'] = '<a href="'.makeUrl($paths->fullpage, 'sub=admin&amp;action=delete&amp;id='.$comment['comment_id']).'">Delete</a>';
    
    // Signature
    $strings['SIGNATURE'] = '';
    if($comment['signature'] != '') $strings['SIGNATURE'] = RenderMan::render($comment['signature']);
    
    $bool['auth_mod']  = $auth_mod;
    $bool['can_edit']  = $auth_edit;
    $bool['signature'] = ( $strings['SIGNATURE'] == '' ) ? false : true;
    
    $tpl->assign_vars($strings);
    $tpl->assign_bool($bool);
    echo $tpl->run();
  }

  $sn = $session->user_logged_in ? $session->username . '<input name="name" id="mdgScreenName" type="hidden" value="'.$session->username.'" />' : '<input name="name" id="mdgScreenName" type="text" size="35" />';
  if(getConfig('comments_need_login') == '1')
  {
    $session->kill_captcha();
    $captcha = $session->make_captcha();
  }
  $captcha = ( getConfig('comments_need_login') == '1' && !$session->user_logged_in ) ? '<tr><td>Visual confirmation:<br /><small>Please enter the code you see on the right.</small></td><td><img src="'.makeUrlNS('Special', 'Captcha/'.$captcha).'" alt="Visual confirmation" style="cursor: pointer;" onclick="this.src = \''.makeUrlNS("Special", "Captcha/".$captcha).'/\'+Math.floor(Math.random() * 100000);" /><input name="captcha_id" id="mdgCaptchaID" type="hidden" value="'.$captcha.'" /><br />Code: <input name="captcha_input" id="mdgCaptchaInput" type="text" size="10" /><br /><small><script type="text/javascript">document.write("If you can\'t read the code, click on the image to generate a new one.");</script><noscript>If you can\'t read the code, please refresh this page to generate a new one.</noscript></small></td></tr>' : '';
  
  echo '<div id="commentform_'.$seed.'">
          '.EnanoPress_Separator().'
          <form action="'.makeUrl($paths->fullpage, 'act=postcomment', true).'" method="post">
            <table border="0">
              <tr><td>Your name or screen name:</td><td>'.$sn.'</td></tr>
              <tr><td>Comment subject:</td><td><input name="subj" id="mdgSubject" type="text" size="35" /></td></tr>
              '.$captcha.'
              <tr><td valign="top">Comment text:<br />(most HTML will be stripped)</td><td><textarea name="text" id="mdgCommentArea" rows="10" cols="40"></textarea></td></tr>
              <tr><td colspan="2" style="text-align: center;"><input type="submit" name="__doPostBack" value="Submit Comment" /></td></tr>
            </table>
          </form>
        </div>
        <script type="text/javascript">
          document.getElementById(\'commentform_'.$seed.'\').style.display = \'none\';
        </script>
';
  
  $ret = ob_get_contents();
  ob_end_clean();
  return $ret;
}

function page_Admin_EnanoPress()
{
  global $db, $session, $paths, $template, $plugins; if($session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN) { header('Location: '.makeUrl($paths->nslist['Special'].'Administration'.urlSeparator.'noheaders')); die('Hacking attempt'); }
  echo '<p>Coming soon!</p>';
}

?>