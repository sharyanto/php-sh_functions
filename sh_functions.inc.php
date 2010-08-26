<?

#
# sh_functions.inc.php - librari fungsi
#

# todo:
#
# * activerecord: choices => array('foo'=>array('text1', 'text2')) or something
#   like that. pilihan boleh sama kan, mis: 1=Indonesia, 2=Jerman, 3=Polandia,
#   1=Repulik Indonesia.

$SH_VERSION = "20100821";

require_once dirname(__FILE__).'/sh_translation.inc.php';
#load_lang_file("grid.yaml");
#load_lang_file("activerecord.yaml");

require_once dirname(__FILE__).'/sh_utils.inc.php';
require_once dirname(__FILE__).'/sh_activerecord.inc.php';
require_once dirname(__FILE__).'/sh_grid.inc.php';

?>
