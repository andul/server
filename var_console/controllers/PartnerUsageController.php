<?php
/**
 * @package Var
 * @subpackage Partners
 */
class PartnerUsageController extends Zend_Controller_Action
{
	public function indexAction()
	{
		$request = $this->getRequest();
		$page = $this->_getParam('page', 1);
		$pageSize = $this->_getParam('pageSize', 10);
		$from = new Zend_Date($this->_getParam('from_date', $this->getDefaultFromDate()));
		$to = new Zend_Date($this->_getParam('to_date', $this->getDefaultToDate()));
		
		$client = Infra_ClientHelper::getClient();
		
		$form = new Form_PartnerUsageFilter();
		
		$form->populate($request->getParams());
		
		// when no statuses selected
		if (!$form->getElement('include_active')->getValue() && !$form->getElement('include_blocked')->getValue() && !$form->getElement('include_removed')->getValue())
		{
			$form->getElement('include_active')->setValue(1);
			$form->getElement('include_blocked')->setValue(1);
			$form->getElement('include_removed')->setValue(1);
		}
		
		// init filters
		
		$usageFilter = new Kaltura_Client_Type_ReportInputFilter();
		$usageFilter->fromDate = $from->toString(Zend_Date::TIMESTAMP);
		$usageFilter->toDate = $to->toString(Zend_Date::TIMESTAMP);
		$usageFilter->timeZoneOffset =Infra_AuthHelper::getAuthInstance()->getIdentity()->getTimezoneOffset();
		if ($form->getElement('interval')->getValue())
		{
		    $usageFilter->interval = ($form->getElement('interval')->getValue() == 'daily') ? Kaltura_Client_Enum_ReportInterval::DAYS : Kaltura_Client_Enum_ReportInterval::MONTHS;
		}
		
		$varConsolePlugin = Kaltura_Client_VarConsole_Plugin::get($client);
		
		// get results and paginate
		$partnerFilter = $this->getPartnerFilterFromForm($form);
		$paginatorAdapter = new Form_PartnerUsageFilterPaginator($varConsolePlugin->varConsole, "getPartnerUsage", null, $partnerFilter, $usageFilter);
		$paginator = new Form_PartnerUsagePaginator($paginatorAdapter, $request);
		$paginator->setCurrentPageNumber($page);
		$paginator->setItemCountPerPage($pageSize);
		// set view
		$this->view->from = $from;
		$this->view->to = $to;
		$this->view->form = $form;
		$this->view->paginator = $paginator;
	}
	
	public function exportCsvAction() 
	{
		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender();
		$request = $this->getRequest();
		$from = new Zend_Date($this->_getParam('from_date', $this->getDefaultFromDate()));
		$to = new Zend_Date($this->_getParam('to_date', $this->getDefaultToDate()));
		$client = Infra_ClientHelper::getClient();
		if ($client->getKs() == null) {
			$client->setKs(self::generateKs());
		}
		$form = new Form_PartnerUsageFilter();
		$form->populate($request->getParams());
		
		// init filters
		$partnerFilter = $this->getPartnerFilterFromForm($form);
		$usageFilter = new Kaltura_Client_Type_ReportInputFilter();
		$usageFilter->fromDate = $from->toString(Zend_Date::TIMESTAMP);
		$usageFilter->toDate = $to->toString(Zend_Date::TIMESTAMP);
		$usageFilter->timeZoneOffset =Infra_AuthHelper::getAuthInstance()->getIdentity()->getTimezoneOffset();
		
		$pager = new Kaltura_Client_Type_FilterPager();
		$pager->pageIndex = 1;
		$pager->pageSize = 500;
		
		$varConsolePlugin = Kaltura_Client_VarConsole_Plugin::get($client);
		
		$items = array();
		while(true)
		{
		    $response = $varConsolePlugin->varConsole->getPartnerUsage($partnerFilter, $usageFilter, $pager);
			if (count($response->objects) <= 0)
				break;
				
			foreach($response->objects as &$object)
				$items[] = $object;
			$pager->pageIndex++;
		}
		
		$format = $this->view->translate('csv date');
		$fileName = 'Usage report '.$from->toString($format).' to '.$to->toString($format).'.csv';
		header('Content-type: text/csv');
		header('Content-Disposition: attachment; filename="'.$fileName.'"');

		// echo the csv header
		echo 	$this->view->translate('partner-usage table partnerStatus'), ',',
				$this->view->translate('partner-usage table partnerName'), ',',
				$this->view->translate('partner-usage table partnerId'), ',',
				$this->view->translate('partner-usage table partnerCreatedAt'), ',',
				$this->view->translate('partner-usage table bandwidth'), ',',
				//$this->view->translate('partner-usage table totalStorage'), ',',
				$this->view->translate('partner-usage table storage'),
				$this->view->translate('partner-usage table peakStorage'),
				$this->view->translate('partner-usage table avgStorage'),
				$this->view->translate('partner-usage table combinedBandwidthStorage'),
				"\r\n";

		// echo the csv data
		foreach($items as $item)
		{
			$d = (new Zend_Date($item->partnerCreatedAt));
			echo 	$this->view->enumTranslate('KalturaPartnerStatus', $item->partnerStatus), ',',
					$item->partnerName, ',', 
					$item->partnerId, ',', 
					'"',$d->toString(Zend_Date::DATE_LONG), '",', 
					(int)$item->bandwidth, ',',
					//(int)$item->totalStorage, ',',
					(int)$item->storage,
					(int)$item->peakStorage,
					(int)$item->avgStorage,
					(int)$item->combinedStorageBandwidth,
					"\r\n";
		}
	}
	
	private function getPartnerFilterFromForm(Zend_Form $form)
	{
		$filter = new Kaltura_Client_Type_PartnerFilter();
		$filterType = $form->getValue('filter_type');
		$filterInput = $form->getValue('filter_input');
		$includeActive = $form->getValue('include_active');
		$includeBlocked = $form->getValue('include_blocked');
		$includeRemoved = $form->getValue('include_removed');
		
		if ($filterType == 'byid')
		{
			$filter->idIn = $filterInput;
		}
		else
		{
			if ($filterType == 'byname')
				$filter->nameLike = $filterInput;
			elseif ($filterType == 'free' && $filterInput)
				$filter->partnerNameDescriptionWebsiteAdminNameAdminEmailLike = $filterInput;
		}
		$statuses = array();
		if ($includeActive)
			$statuses[] = Kaltura_Client_Enum_PartnerStatus::ACTIVE;
		if ($includeBlocked)
			$statuses[] = Kaltura_Client_Enum_PartnerStatus::BLOCKED;
		if ($includeRemoved)
			$statuses[] = Kaltura_Client_Enum_PartnerStatus::FULL_BLOCK;
						
		$filter->statusIn = implode(',', $statuses);
		$filter->orderBy = Kaltura_Client_Enum_PartnerOrderBy::ID_DESC;
		return $filter;
	}
	
	private function getDefaultFromDate()
	{
		return 0;
	}
	
	private function getDefaultToDate()
	{
		return time();
	}
	
	private static function generateKs() {
		$settings = Zend_Registry::get('config')->settings;
		$partnerId = $settings->partnerId;
		$secret = $settings->secret;
		$sessionExpiry = $settings->sessionExpiry;
		return self::createKS($partnerId, $secret, Kaltura_Client_Enum_SessionType::ADMIN, $sessionExpiry);
	}
	
	private static function createKS($partnerId, $adminSecret, $sessionType = Kaltura_Client_Enum_SessionType::ADMIN, $expiry = 7200)
	{
		$puserId = '';
		$privileges = '';
		
		$rand = rand(0, 32000);
		$rand = microtime(true);
		$expiry = time() + $expiry;
		$fields = array($partnerId, '', $expiry, $sessionType, $rand, $puserId, $privileges);
		$str = implode(";", $fields);
		
		$salt = $adminSecret;
		$hashed_str = self::hash($salt, $str) . "|" . $str;
		$decoded_str = base64_encode($hashed_str);
		
		return $decoded_str;
	}
	
	private static function hash($salt, $str)
	{
		return sha1($salt . $str);
	}
}