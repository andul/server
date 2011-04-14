<?php
/**
 * @package plugins.bulkUploadXml
 */
class BulkUploadXmlPlugin extends KalturaPlugin implements IKalturaEnumerator, IKalturaObjectLoader
{
	const PLUGIN_NAME = 'bulkUploadXml';
	
	/**
	 * 
	 * Returns the plugin name
	 */
	public static function getPluginName()
	{
		return self::PLUGIN_NAME;
	}
		
	/**
	 * @return array<string> list of enum classes names that extend the base enum name
	 */
	public static function getEnums($baseEnumName = null)
	{
		if(is_null($baseEnumName))
			return array('BulkUploadXmlType');
		
		if($baseEnumName == 'BulkUploadType')
			return array('BulkUploadXmlType');
			
		return array();
	}
	
	/**
	 * @param string $baseClass
	 * @param string $enumValue
	 * @param array $constructorArgs
	 * @return object
	 */
	public static function loadObject($baseClass, $enumValue, array $constructorArgs = null)
	{
		//Gets the right job for the engine	(only for server)
		if($baseClass == 'kBulkUploadJobData' && $enumValue == self::getBulkUploadTypeCoreValue(BulkUploadXmlType::XML))
			return new kBulkUploadCsvJobData();
		
		//Gets the right job for the engine (only for Server)
		if($baseClass == 'KalturaBulkUploadJobData')
		{
			if($enumValue == self::getBulkUploadTypeCoreValue(BulkUploadXmlType::XML))
			{
				return new KalturaBulkUploadCsvJobData();
			}
		}
			
		//Gets the right job for the engine (only for clients)	
		if(class_exists('KalturaClient') && $baseClass == 'KalturaBulkUploadJobData')
		{
			if($enumValue == self::getBulkUploadTypeCoreValue(BulkUploadXmlType::XML))
				return new KalturaBulkUploadCsvJobData();
		}
		
		//Gets the engine (only for clients)
		if(class_exists('KalturaClient') && $baseClass == 'KBulkUploadEngine')
		{
			if($enumValue == KalturaBulkUploadType::XML)
			{
				list($taskConfig, $kClient, $job) = $constructorArgs;
				return new BulkUploadEngineCsv($taskConfig, $kClient, $job);
			}
		}
				
		return null;
		
	
		return null;
	}
	
	/**
	 * @param string $baseClass
	 * @param string $enumValue
	 * @return string
	 */
	public static function getObjectClass($baseClass, $enumValue)
	{
		return null;
	}
		
	/**
	 * @return int id of dynamic enum in the DB.
	 */
	public static function getBulkUploadTypeCoreValue($valueName)
	{
		$value = self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
		return kPluginableEnumsManager::apiToCore('BulkUploadType', $value);
	}
	
	/**
	 * @return string external API value of dynamic enum.
	 */
	public static function getApiValue($valueName)
	{
		return self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
	}
}
