<?php
/**
 * TestLink Issue from GitHub Plugin
 * This script is distributed under the GNU General Public License 3 or later.
 *
 * @filesource  IssueFromGitHub.php
 * @copyright   2021, naofum
 * @link        https://github.com/naofum/testlink-issue-from-github
 *
 */

require_once(TL_ABS_PATH . '/lib/functions/tlPlugin.class.php');

require_once('common.php');
require_once('exec.inc.php');

/**
 * Class IssueFromGitHubPlugin
 */
class IssueFromGitHubPlugin extends TestlinkPlugin
{
  function _construct()
  {

  }

  function register()
  {
    $this->name = 'IssueFromGitHub';
    $this->description = 'IssueFromGitHub Plugin';

    $this->version = '0.1';

    $this->author = 'naofum';
    $this->contact = '';
    $this->url = 'https://github.com/naofum/testlink-issue-from-github';
  }

  function config()
  {
    return array();
  }

  function hooks()
  {
    $hooks = array(
      'EVENT_LEFTMENU_BOTTOM' => 'bottom_link'
    );
    return $hooks;
  }

  function bottom_link()
  {
	$tLink['href'] = plugin_page('import.php');
	$tLink['label'] = plugin_lang_get('import');

    return $tLink;
  }

}
