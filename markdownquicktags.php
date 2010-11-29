<?php
/*
Plugin Name: Markdown QuickTags
Plugin URI: http://brettterpstra.com/code/markdown-quicktags/
Description: Replaces the WordPress QuickTags with Markdown-compatible ones
Version: 0.7.2
Author: Brett Terpstra
Author URI: http://brettterpstra.com

    Copyright 2010  Brett Terpstra  (email : me@brettterpstra.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( !function_exists ('add_action') ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

if ( function_exists('add_action') ) {
	// Pre-2.6 compatibility
	if ( !defined('WP_CONTENT_URL') )
		define( 'WP_CONTENT_URL', get_option('url') . '/wp-content');
	if ( !defined('WP_CONTENT_DIR') )
		define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	if ( !defined('WP_CONTENT_FOLDER') )
		define( 'WP_CONTENT_FOLDER', str_replace(ABSPATH, '/', WP_CONTENT_DIR) );
}

$mdQuickTags = new MarkdownQuickTags();

class MarkdownQuickTags {
	function trailingslashit($string) {
      if ( '/' != substr($string, -1)) {
          $string .= '/';
      }
      return $string;
  }
	function MarkdownQuickTags() {
	  $plugin_dir = basename(dirname(__FILE__));
		$this->siteurl = $this->trailingslashit(get_option('siteurl'));;
		$this->js_path =  $this->siteurl . "wp-content/plugins/$plugin_dir/js/";
    $this->css_path =  $this->siteurl . "wp-content/plugins/$plugin_dir/css/";
    
		add_action( 'admin_print_scripts', array(&$this, 'js_libs' ));
		add_action( 'wp_ajax_markdownify', array(&$this, 'markdownify' ));
		add_action( 'wp_ajax_markdown', array(&$this, 'markdown' ));
		add_action( 'wp_ajax_mdqt_options', array(&$this, 'getoptions' ));
		add_action( 'admin_init', array(&$this, 'mdqt_admin_init' ));
    add_action( 'admin_print_styles', array(&$this, 'mdqt_admin_styles' ) );
    register_activation_hook(__FILE__, array(&$this, 'mdqt_activation_function' ));
	}
	
  function mdqt_activation_function() {
    add_option( 'mdqt_fullscreenwidth',800 );
  	add_option( 'mdqt_fullscreenbg','rgba(25,25,25,0.9)' );
  	add_option( 'mdqt_fullscreenbgopacity','85' );
  	add_option( 'mdqt_usesmarty',true );
  	add_option( 'mdqt_showtooltips',true );
  	add_option( 'mdqt_showlookup',false );
  	add_option( 'mdqt_font','Helvetica Neue');
  	add_option( 'mdqt_tabsize',4 );
  	add_option( 'mdqt_fontsize',14);
  }
  
  function mdqt_admin_init() {
    wp_register_style( 'mdqt_style', $this->css_path . 'mdqt_style.css' );
    wp_register_style( 'peppergrinder', $this->css_path . 'peppergrinder.css');
    
    // wp_register_style( 'nobile', 'http://fonts.googleapis.com/css?family=Nobile:regular&subset=latin');
    function mdqt_image_tag($html, $id , $alt, $title) {
      preg_match('/src="(.*?)"/', $html, $matches, PREG_OFFSET_CAPTURE);
      $imgurl = $matches[1][0];
      $html = "![$title]($imgurl \"$alt\")";
      return $html;
    }
    // add_filter('get_image_tag','mdqt_image_tag',10,4);
  }

  function mdqt_admin_styles() {
    wp_enqueue_style( 'mdqt_style' );
    wp_enqueue_style( 'farbtastic' );
    wp_enqueue_style( 'peppergrinder' );
    // wp_enqueue_style( 'nobile' );
  }
	
	function js_libs() {
		if ( is_admin() ) {
		  wp_enqueue_script('jquery');
		  wp_enqueue_script('jquery-ui-core');
		  wp_enqueue_script('jquery-ui-dialog');
      wp_enqueue_script('farbtastic');
      wp_deregister_script('quicktags');
      wp_register_script('quicktags', $this->js_path.'quicktags.jquery.js', array('jquery','jquery-ui-dialog','jquery-ui-core','mdqt_tabor','mdqt_textchange','mdqt_atools','mdqt_asuggest','mdqt_hoverintent','mdqt_tipsy'), true);
      wp_enqueue_script('mdqt_textchange',$this->js_path.'textchange.jquery.js', array('jquery'), null, false);
      wp_enqueue_script('mdqt_atools',$this->js_path.'jquery.a-tools.min.js', array('jquery'), null, false);
      wp_enqueue_script('mdqt_asuggest',$this->js_path.'jquery.asuggest.js', array('jquery'), null, false);
      wp_enqueue_script('mdqt_hoverintent',$this->js_path.'jquery.hoverIntent.min.js', array('jquery'), null, false);
      wp_enqueue_script('mdqt_tabor',$this->js_path.'taboverride.js', array('jquery'), null, false);
      wp_enqueue_script('mdqt_tipsy',$this->js_path.'jquery.tipsy.js', array('jquery'), null, false);
    }
	}
	
	function markdownify() {
		require_once(dirname(__FILE__).'/markdownify/markdownify_extra.php');
    if (!empty($_REQUEST['input'])) {
      $md = new Markdownify_Extra(true, MDFY_BODYWIDTH, true);
  		$input = stripslashes($_REQUEST['input']);
  		$output['markdown'] = $md->parseString($input);
      die(json_encode($output));
    } else {
      die("Empty request");
    }
	}
	
	function markdown() {
	  $markdowninstalled = true;
	  $smartyinstalled = true;
	  if (!function_exists('Markdown') && is_admin()) {
	    require_once(dirname(__FILE__)."/php/markdown.php");
	    $markdowninstalled = false;
	  }
	  if (!function_exists('SmartyPants') && is_admin() && get_option('mdqt_usesmarty') == true) {
	    require_once(dirname(__FILE__)."/php/smartypants.php");
	    $smartyinstalled = false;
	  }
	  if (!empty($_REQUEST['input'])) {
  	  $input = stripslashes($_REQUEST['input']);

  	  if ($_REQUEST['type'] == 'preview') {
    	  if ($markdowninstalled) {
    	    $output['html'] = apply_filters('the_content', $input);
    	  } else {
    	    $output['html'] = apply_filters('the_content',Markdown($input));
    	  }
    	  if (get_option('mdqt_usesmarty') == true)
    	    $output['html'] = SmartyPants($output['html']);
        die(json_encode($output));
      } else {
        $output['html'] = Markdown($input);
        die(json_encode($output));
      }
    } else {
      die("Empty request");
    }  
	}
	
	function getoptions()
	{
	  echo json_encode(array(
	    'fullscreenwidth' => get_option('mdqt_fullscreenwidth'),
	    'fullscreenbg' => get_option('mdqt_fullscreenbg'),
	    'fullscreenbgopacity' => (get_option('mdqt_fullscreenbgopacity') / 100),
	    'showlookup' => get_option('mdqt_showlookup'),
	    'showtooltips' => get_option('mdqt_showtooltips'),
	    'tabsize' => get_option('mdqt_tabsize'),
	    'font' => get_option('mdqt_font'),
	    'fontsize' => get_option('mdqt_fontsize')
	  ));
	  die;
	}
}
// create custom plugin settings menu
add_action('admin_menu', 'mdqt_create_menu');

function mdqt_create_menu() {
	add_submenu_page('options-general.php','Markdown QuickTags Settings', 'Markdown QuickTags', 'administrator', __FILE__, 'mdqt_settings_page');
	add_action( 'admin_init', 'register_mdqt_settings' );
}

function register_mdqt_settings() {
	//register our settings
	register_setting( 'mdqt-settings-group', 'mdqt_fullscreenwidth' );
	register_setting( 'mdqt-settings-group', 'mdqt_fullscreenbg' );
	register_setting( 'mdqt-settings-group', 'mdqt_fullscreenbgopacity' );
	register_setting( 'mdqt-settings-group', 'mdqt_usesmarty' );
	register_setting( 'mdqt-settings-group', 'mdqt_showtooltips' );
	register_setting( 'mdqt-settings-group', 'mdqt_showlookup' );
	register_setting( 'mdqt-settings-group', 'mdqt_tabsize' );
	register_setting( 'mdqt-settings-group', 'mdqt_font' );
	register_setting( 'mdqt-settings-group', 'mdqt_fontsize' );
}

function mdqt_settings_page() {
?>
<script type="text/javascript" charset="utf-8">
  jQuery(document).ready(function($) {
    $('#colorpicker').farbtastic('#mdqt_fullscreenbg');

    $('#mdqt_fullscreenbg').focus(function(){
      $('#colorpicker').fadeIn('fast');
    }).blur(function(){
      $('#colorpicker').hide();
      var input = $('#mdqt_fullscreenbg').val();
      if (!validHex(input)) {
        if (/^([0-9a-f]{3}){1,2}$/i.test(input))
          $('#mdqt_fullscreenbg').val('#'+input);
        else
          $('#mdqt_fullscreenbg').val("<?php echo get_option('mdqt_fullscreenbg'); ?>");
      }
    });
    
    $('#mdqt_fullscreenwidth').blur(function(){
      var input = $(this).val();
      if (/^\d{3,4}$/.test(input) && input >= 100 && input <= 1900) {
        return true;
      } else {
        if (input < 100)
          $(this).val('100');
        else if (input > 1900)
          $(this).val('1900');
        else
          $(this).val("<?php echo get_option('mdqt_fullscreenwidth'); ?>");
      }
      return true;
    });
    
    $('#mdqt_fullscreenbgopacity').blur(function(){
      var input = $(this).val();
      if (/^\d{2,3}$/.test(input) && input >= 10 && input <= 100) {
        return true;
      } else {
        if (input < 10)
          $(this).val('10');
        else if (input > 100)
          $(this).val('100');
        else
          $(this).val("<?php echo get_option('mdqt_fullscreenbgopacity'); ?>");
      }
      return true;
    });
  });
  function validHex(hexcolor) { 
    var strPattern = /^#([0-9a-f]{3}){1,2}$/i; 
    return strPattern.test(hexcolor); 
  }
</script>
<div class="wrap">
<h2>Markdown QuickTags</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'mdqt-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Full screen editor width</th>
        <td><input type="text" id="mdqt_fullscreenwidth" name="mdqt_fullscreenwidth" value="<?php echo get_option('mdqt_fullscreenwidth'); ?>" maxlength="4" size="4" />px</td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Full screen editor background color</th>
        <td><div style="position:relative;float:left"><input type="text" id="mdqt_fullscreenbg" name="mdqt_fullscreenbg" value="<?php echo get_option('mdqt_fullscreenbg'); ?>" maxlength="7" size="7" />
              <div id="colorpicker" style="display:none;position:absolute;right:-200px;top:-80px"></div></div>
          </td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Full screen editor background opacity</th>
        <td>
           <input id="mdqt_fullscreenbgopacity" type="text" name="mdqt_fullscreenbgopacity" id="mdqt_fullscreenbgopacity" value="<?php echo get_option('mdqt_fullscreenbgopacity'); ?>" maxlength="3" size="3">%
        </td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Tab size</th>
        <td>
           <select name="mdqt_tabsize" id="mdqt_tabsize" size="1">
              <option value="2"<?php selected( get_option('mdqt_tabsize'), '2' ); ?>>2</option>
              <option value="3"<?php selected( get_option('mdqt_tabsize'), '3' ); ?>>3</option>
              <option value="4"<?php selected( get_option('mdqt_tabsize'), '4' ); ?>>4</option>
              <option value="5"<?php selected( get_option('mdqt_tabsize'), '5' ); ?>>5</option>
            </select> spaces
        </td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Use SmartyPants for Preview/Render?</th>
        <td><input type="checkbox" name="mdqt_usesmarty" value="1" <?php checked(get_option('mdqt_usesmarty'), 1); ?> /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Show Tooltips?</th>
        <td><input type="checkbox" name="mdqt_showtooltips" value="1" <?php checked(get_option('mdqt_showtooltips'), 1); ?> /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Show "lookup" button?</th>
        <td><input type="checkbox" name="mdqt_showlookup" value="1" <?php checked(get_option('mdqt_showlookup'), 1); ?> /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Editor font</th>
        <td><select name="mdqt_font" id="mdqt_font" size="1">
          <option value="Arial"<?php selected( get_option('mdqt_font'), 'Arial' ); ?>>Arial</option>
          <option value="Helvetica Neue"<?php selected( get_option('mdqt_font'), 'Helvetica Neue' ); ?>>Helvetica Neue</option>
          <option value="Georgia"<?php selected( get_option('mdqt_font'), 'Georgia' ); ?>>Georgia</option>
          <option value="AnonymousProRegular"<?php selected( get_option('mdqt_font'), 'AnonymousProRegular' ); ?>>Anonymous Pro</option>
          <option value="CantarellRegular"<?php selected( get_option('mdqt_font'), 'CantarellRegular' ); ?>>Cantarell</option>
          <option value="BitstreamVeraSansRoman"<?php selected( get_option('mdqt_font'), 'BitstreamVeraSansRoman' ); ?>>Bitstream Vera Sans</option>
          <option value="YanoneKaffeesatzRegular"<?php selected( get_option('mdqt_font'), 'YanoneKaffeesatzRegular' ); ?>>YanoneKafeesatz</option>
        </select></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Editor font size</th>
        <td>
          <select name="mdqt_fontsize" id="mdqt_fontsize" size="1">
            <option value="10"<?php selected( get_option('mdqt_fontsize'), '10' ); ?>>10</option>
            <option value="12"<?php selected( get_option('mdqt_fontsize'), '12' ); ?>>12</option>
            <option value="14"<?php selected( get_option('mdqt_fontsize'), '14' ); ?>>14</option>
            <option value="18"<?php selected( get_option('mdqt_fontsize'), '18' ); ?>>18</option>
          </select>
        </td>
        </tr>
        
    </table>
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
    

</form>
</div>
<?php } ?>
