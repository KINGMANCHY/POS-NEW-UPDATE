<?php
if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) require_once dirname(__DIR__, 2) . '/config/config.php';
if (!defined('POS_APP')) die('Direct access not permitted.');
class CustomerReportController
{
    private Customer $customers;
    public function __construct() { $this->customers = new Customer(); }
    public function dispatch(): void {
        SessionManager::requireRole(['Administrator', 'Manager']); $action=$_REQUEST['action']??'';
        $search=Security::sanitize(trim($_GET['search']??'')); $from=Security::sanitize($_GET['date_from']??''); $to=Security::sanitize($_GET['date_to']??''); $customerId=max(0,(int)($_GET['customer_id']??0));
        if ($action==='customers') Helper::jsonResponse(true,'',['customers'=>$search !== '' ? $this->customers->searchForReport($search) : []]);
        if ($action==='details') { if ($customerId <= 0) Helper::jsonResponse(false,'Select a customer first.',[],422); $details=$this->customers->reportDetails($customerId,$from,$to); if (!$details) Helper::jsonResponse(false,'Customer not found.',[],404); Helper::jsonResponse(true,'',$details); }
        if ($action==='list') Helper::jsonResponse(true,'',['rows'=>$this->customers->report($search,$from,$to,$customerId)]);
        if (!in_array($action,['export_excel','export_pdf'],true)) Helper::jsonResponse(false,'Unknown action.',[],400);
        $rows=array_map(static fn($r)=>[$r['full_name'],$r['phone'],$r['email'],$r['loyalty_points'],$r['points_earned'],$r['points_redeemed'],$r['transaction_count'],(float)$r['total_spent'],$r['last_purchase']?:''], $this->customers->report($search,$from,$to,$customerId));
        $headers=['Customer','Phone','Email','Point Balance','Points Earned','Points Redeemed','Transactions','Total Spent','Last Purchase'];
        if ($action==='export_excel') XlsxWriter::stream('customer_report_'.date('Ymd_His'),$headers,$rows,'Customers');
        PdfWriter::stream('customer_report_'.date('Ymd_His'),'Customer Report','Generated '.date('Y-m-d H:i'),$headers,[62,45,72,42,42,50,48,55,50],$rows);
    }
}
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) { SessionManager::start(); (new CustomerReportController())->dispatch(); }
