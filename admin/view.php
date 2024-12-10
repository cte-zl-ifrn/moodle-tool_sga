<?php
namespace local_sga\admin;

require_once(\dirname(\dirname(\dirname(__DIR__))) . '/config.php');

$PAGE->set_url(new \moodle_url('/local/sga/admin/view.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title('SGA Sync Admin :: View');
  
if (!is_siteadmin()) {
    echo $OUTPUT->header();
    echo "Fazes o quê aqui?";
    echo $OUTPUT->footer();
    die();
}

 echo $OUTPUT->header();
$linha = $DB->get_record("sga_enrolment_to_sync", ['id'=>$_GET['id']]);
$statuses = [0=>"Não processado", 1=>"Sucesso", 2=>'Falha'];
$linha->status = $statuses[$linha->processed];
$templatecontext = ['linha' => $linha];
echo $OUTPUT->render_from_template('local_sga/view', $templatecontext);
echo $OUTPUT->footer();
