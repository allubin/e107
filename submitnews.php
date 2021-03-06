<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2013 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 */

require_once("class2.php");
include_lan(e_LANGUAGEDIR.e_LANGUAGE.'/lan_'.e_PAGE);

require_once(HEADERF);

if (!isset($pref['subnews_class']))
{
	$pref['subnews_class'] = e_UC_MEMBER;
}

if (!check_class($pref['subnews_class']))
{
	$ns->tablerender(NWSLAN_12, NWSLAN_11);
	require_once(FOOTERF);
	exit;
}

if (isset($_POST['submitnews_submit']) && $_POST['submitnews_title'] && $_POST['submitnews_item'])
{
	$ip = e107::getIPHandler()->getIP(FALSE);
	$fp = new floodprotect;
	if ($fp->flood("submitnews", "submitnews_datestamp") == FALSE)
	{
		header("location:".e_BASE."index.php");
		exit;
	}

	$submitnews_user  = (USER ? USERNAME  : trim($tp->toDB($_POST['submitnews_name'])));
	$submitnews_email = (USER ? USEREMAIL : trim(check_email($tp->toDB($_POST['submitnews_email']))));
	$submitnews_title = $tp->toDB($_POST['submitnews_title']);
	$submitnews_item  = $tp->toDB($_POST['submitnews_item']);
	$submitnews_item  = str_replace("src=&quot;e107_images", "src=&quot;".SITEURL."e107_images", $submitnews_item);
	$submitnews_file  = "";
	$submitnews_error = FALSE;
	if (!$submitnews_user || !$submitnews_email)
	{
		$message = SUBNEWSLAN_7;
		$submitnews_error = TRUE;
	}

	// ==== Process File Upload ====
	if (FILE_UPLOADS && $_FILES['file_userfile'] && varsettrue($pref['subnews_attach']) && varsettrue($pref['upload_enabled']) && check_class($pref['upload_class']))
	{
		require_once(e_HANDLER.'upload_handler.php');
		$uploaded = process_uploaded_files(e_UPLOAD, FALSE, array('file_mask' => 'jpg,gif,png', 'max_file_count' => 1));
	
		if (($uploaded === FALSE) || !is_array($uploaded))
		{	// Non-specific error
			$submitnews_error = TRUE;
			$message = SUBNEWSLAN_8;
		}
		else
		{
			$submitnews_filearray = array();
			
			foreach($uploaded as $c=>$v)
			{
				if (varset($uploaded[$c]['error'],0) != 0)
				{
					$submitnews_error = TRUE;
					$message = handle_upload_messages($uploaded);
				}
				else
				{
					if (isset($uploaded[$c]['name']) && isset($uploaded[$c]['type']) && isset($uploaded[$c]['size']))
					{
						$filename = $uploaded[$c]['name'];
						$filetype = $uploaded[$c]['type'];
						$filesize = $uploaded[$c]['size'];
						$fileext  = substr(strrchr($filename, "."), 1);
						$today = getdate();
						$submitnews_file = USERID."_".$today[0]."_".$c."_".str_replace(" ", "_", substr($submitnews_title, 0, 6)).".".$fileext;
								
						if (is_numeric($pref['subnews_resize']) && ($pref['subnews_resize'] > 30)  && ($pref['subnews_resize'] < 5000))
						{
							require_once(e_HANDLER.'resize_handler.php');
					
							if (!resize_image(e_UPLOAD.$filename, e_UPLOAD.$submitnews_file, $pref['subnews_resize']))
							{
							  rename(e_UPLOAD.$filename, e_UPLOAD.$submitnews_file);
							}
						}
						elseif ($filename)
						{
							rename(e_UPLOAD.$filename, e_UPLOAD.$submitnews_file);
						}
					}
				}
	
				if ($filename && file_exists(e_UPLOAD.$submitnews_file))
				{
					$submitnews_filearray[] = $submitnews_file;	
				}
				
			}
		}
		
	}

	if ($submitnews_error === FALSE)
	{
		$sql->db_Insert("submitnews", "0, '$submitnews_user', '$submitnews_email', '$submitnews_title', '".intval($_POST['cat_id'])."', '$submitnews_item', '".time()."', '$ip', '0', '".implode(',',$submitnews_filearray)."' ");
		$edata_sn = array("user" => $submitnews_user, "email" => $submitnews_email, "itemtitle" => $submitnews_title, "catid" => intval($_POST['cat_id']), "item" => $submitnews_item, "image" => $submitnews_file, "ip" => $ip);
		$e_event->trigger("subnews", $edata_sn);
		$ns->tablerender(LAN_133, "<div style='text-align:center'>".LAN_134."</div>");
		require_once(FOOTERF);
		exit;
	}
	else
	{
		message_handler("P_ALERT", $message);
	}
}

if (!defined("USER_WIDTH")) { define("USER_WIDTH","width:95%"); }

$text = "
<div style='text-align:center'>
  <form id='dataform' method='post' action='".e_SELF."' enctype='multipart/form-data' onsubmit='return frmVerify()'>
    <table style='".USER_WIDTH."' class='table fborder'>";

if (!empty($pref['news_subheader']))
{
  $text .= "
  <tr>
    <td colspan='2' class='forumheader3'>".$tp->toHTML($pref['news_subheader'], TRUE, "TITLE")."<br /></td>
  </tr>";
}

if (!USER)
{
  $text .= "
  <tr>
    <td style='width:20%' class='forumheader3'>".LAN_7."</td>
    <td style='width:80%' class='forumheader3'>
      <input class='tbox' type='text' name='submitnews_name' size='60' value='".$tp->toHTML($submitnews_user,FALSE,'USER_TITLE')."' maxlength='100' />
    </td>
  </tr>
  <tr>
    <td style='width:20%' class='forumheader3'>".LAN_112."</td>
    <td style='width:80%' class='forumheader3'>
      <input class='tbox' type='text' name='submitnews_email' size='60' value='".$tp->toHTML($submitnews_email, FALSE, 'LINKTEXT')."' maxlength='100' />
    </td>
  </tr>";
}

$text .= "
<tr>
  <td style='width:20%' class='forumheader3'>".NWSLAN_6.": </td>
	<td style='width:80%' class='forumheader3'>";

if (!$sql->db_Select("news_category"))
{
	$text .= NWSLAN_10;
}
else
{
	$text .= "
		<select name='cat_id' class='tbox'>";
	while (list($cat_id, $cat_name, $cat_icon) = $sql->db_Fetch(MYSQL_NUM))
	{
		$sel = (varset($_POST['cat_id'],'') == $cat_id) ? "selected='selected'" : "";
		$text .= "<option value='{$cat_id}' {$sel}>".$tp->toHTML($cat_name, FALSE, "defs")."</option>";
	}
	$text .= "</select>";
}

$text .= "
  </td>
</tr>
<tr>
  <td style='width:20%' class='forumheader3'>".LAN_62."</td>
	<td style='width:80%' class='forumheader3'>
    <input class='tbox' type='text' id='submitnews_title' name='submitnews_title' size='60' value='".$tp->toHTML(vartrue($_POST['submitnews_title']),TRUE,'USER_TITLE')."' maxlength='200' style='width:90%' />
	</td>
</tr>";

if (e_WYSIWYG)
{
  $insertjs = "rows='25'";
}
else
{
  require_once(e_HANDLER."ren_help.php");
  $insertjs = "rows='15' onselect='storeCaret(this);' onclick='storeCaret(this);' onkeyup='storeCaret(this);'";
}

$text .= "
<tr>
  <td style='width:20%' class='forumheader3'>".LAN_135."</td>
	<td style='width:80%' class='forumheader3'>
    <textarea class='e-wysiwyg tbox' id='submitnews_item' name='submitnews_item' cols='80' style='max-width:95%' {$insertjs}>".$tp->toForm(vartrue($_POST['submitnews_item']),TRUE,'USER_BODY')."</textarea><br />"; 
    // toHTML is dangerous. 

if (!e_WYSIWYG)
{
  $text .= display_help("helpb","submitnews");
}

$text .= "
  </td>
</tr>";

if ($pref['subnews_attach'] && $pref['upload_enabled'] && check_class($pref['upload_class']) && FILE_UPLOADS)
{
  $text .= "
  <tr>
    <td style='width:20%' class='forumheader3'>".SUBNEWSLAN_5."<br /><span class='smalltext'>".SUBNEWSLAN_6."</span></td>
    <td style='width:80%' class='forumheader3'>
      <input class='tbox' type='file' name='file_userfile[]' style='width:90%' multiple='multiple' />
    </td>
  </tr>";
}

$text .= "
      <tr>
        <td colspan='2' style='text-align:center' class='forumheader'>
          <input class='btn btn-success button' type='submit' name='submitnews_submit' value='".LAN_136."' />
        </td>
      </tr>
    </table>
  </form>
</div>";

$ns->tablerender(LAN_136, $text);
require_once(FOOTERF);

function headerjs()
{
  $adder = "";
  if (e_WYSIWYG) $adder = 'tinyMCE.triggerSave();';
  $script = "
  <script type=\"text/javascript\">
  function frmVerify()
  {
    {$adder}
    if(document.getElementById('submitnews_title').value == \"\")
    {
    alert('".SUBNEWSLAN_1."');
    return false;
    }

    if(document.getElementById('submitnews_item').value == \"\")
    {
    alert('".SUBNEWSLAN_2."');
    return false;
    }
  }
  </script>";
  return $script;
}

?>
