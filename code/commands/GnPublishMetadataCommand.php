<?php
/**
 * @author Rainer Spittel (rainer at silverstripe dot com)
 * @package geocatalog
 * @subpackage commands
 */

/**
 * Perform a insert request to a GeoNetwork node.
 */
class GnPublishMetadataCommand extends GnAuthenticationCommand {

	public function get_catalogue_url() {
		$config = Config::inst()->get('Catalogue', 'geonetwork');
		return $config[$config['api_version']]['url_publish'];
	}

	/**
	 * Command execute
	 *
	 * Performs the command to insert/add new metadata. This command creates a 
	 * request (initiates a sub-command) and uses this to send of the 
	 * OGC request to GeoNetwork.
	 *
	 * @see CreateInsertCommand
	 *
	 * @return string OGC CSW response
	 */
	public function execute() {
		$data       = $this->getParameters();
		$gnID 		= $data['gnID'];

		$restfulService = $this->getRestfulService();

		// build the parameters for the publish request. It is a structure of
		// a geonetwork form to publish the data to non-registered users and
		// allow the download of assigned data sources.
		$controller = $this->getController();
		$page = $controller->data();

		$privilegeString = $page->Privilege;
		$privilegeList = explode(',',$privilegeString);

		$groupID = $page->GeonetworkGroupID;

		if ($groupID == '' || $groupID == null) {
			throw new GnPublishMetadataCommand_Exception('Group for record publishing not set correctly. Please contact the system administrator.');
		}

		if ($privilegeString == '' || $privilegeString == null) {
			throw new GnPublishMetadataCommand_Exception('Privileges for publishing not set correctly. Please contact the system administrator.');
		}

		$data = array();
		foreach($privilegeList as $privilege) {
			$data['_1_'.$privilege] = "on";                  // default user (public)
			$data['_'.$groupID.'_'.$privilege] = "on";
		}
		ksort($data);
		$data['id']       = $gnID;

		$params = GnCreateInsertCommand::implode_with_keys($data);

		$headers = array('Content-Type: application/x-www-form-urlencoded');
		$response = $restfulService->request($this->get_catalogue_url(),'POST',$params, $headers);
		$responseXML = $response->getBody();

        // read GeoNetwork ID from the response-XML document
        $doc  = new DOMDocument();
        $doc->loadXML($responseXML);
		$xpath = new DOMXPath($doc);

        $idList = $xpath->query('/response/id');
		$response_gnID = null;
		if ($idList->length > 0) {
			$response_gnID = $idList->item(0)->nodeValue;
		}

		if (!isset($response_gnID)) {
			throw new GnPublishMetadataCommand_Exception('GeoNetwork ID for the new dataset has not been created.');
		}
		if ($gnID != $response_gnID) {
			throw new GnPublishMetadataCommand_Exception('GeoNetwork publication has failed.');
		}
		return $gnID;
	}

}

/**
 * Customised Exception class.
 */
class GnPublishMetadataCommand_Exception extends Exception {}

