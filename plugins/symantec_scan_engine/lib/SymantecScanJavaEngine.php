<?php

class SymantecScanJavaEngine extends SymantecScanEngine
{
	const STATUS_PREFIX = 'Scan Status: ';
	
	private $binFile = 'java -jar /opt/SYMCScan/ssecls/ssecls.jar';
	
	/**
	 * This function should be used to let the engine take specific configurations from the batch job parameters.
	 * For example - command line of the relevant binary file.
	 * @param unknown_type $paramsObject Object containing job parameters
	 */
	public function config($paramsObject)
	{
		if (!isset($paramsObject->symantecScanJavaEngineCmd))
		{
			return false;
		}
		$this->binFile = $paramsObject->symantecScanJavaEngineCmd;
		return true;
	}
	
	/**
	 * Will execute the virus scan for the given file path and return the output from virus scanner program
	 * and the error description
	 * @param string $filePath
	 * @param boolean $cleanIfInfected
	 * @param string $errorDescription
	 */
	public function execute($filePath, $cleanIfInfected, &$output, &$errorDescription)
	{
		if (!$this->binFile)
		{
			$errorDescription = 'Engine command line not set';
			return KalturaVirusScanJobResult::SCAN_ERROR;
		}
		
		if (!file_exists($filePath)) {
			$errorDescription = 'Source file does not exists ['.$filePath.']';
			return KalturaVirusScanJobResult::SCAN_ERROR;
		}
		
		$scanMode = $cleanIfInfected ? 'scanrepair' : 'scan';
		
		$cmd = $this->binFile . " --action $scanMode --verbose -f $filePath";

		$errorDescription = null;
		$output = null;
		
		KalturaLog::debug("Executing - [$cmd]");
		exec($cmd, $output, $return_value);
		
		$found = false;
		foreach ($output as $line)
		{
			if (kString::beginsWith($line, self::STATUS_PREFIX)) {
				$found = $line;
				break;
			}
		}
		
		$output = implode(PHP_EOL, $output);
		
		if (!$found)
		{
			$errorDescription = 'Unknown error';
			return KalturaVirusScanJobResult::SCAN_ERROR;
		}
		
		$returnValue = trim(substr($found, strlen(self::STATUS_PREFIX)));
		
		if ($returnValue == 'Clean')
		{
			return KalturaVirusScanJobResult::FILE_IS_CLEAN;
		}
		else if ($returnValue == 'Repaired')
		{
			return KalturaVirusScanJobResult::FILE_WAS_CLEANED;
		}
		else if ($returnValue == 'Infected')
		{
			return KalturaVirusScanJobResult::FILE_INFECTED;
		}
		else 
		{ 
			$errorDescription = "Unknown returned value from virus scanner";
		}

		return KalturaVirusScanJobResult::SCAN_ERROR;
	}

}