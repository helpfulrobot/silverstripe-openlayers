<?php
/**
 * @author Rainer Spittel (rainer at silverstripe dot com)
 * @package openlayers
 * @subpackage code
 */


/** 
 * Layer class. Each instance of this layer class represents a JavaScript
 * OpenLayer Layer class. It is used to manage and control the map 
 * behaviour.
 */
class OLLayer extends DataObject {
	
	static $db = array (
		"Title"				=> "Varchar(50)",
		"Url" 				=> "Varchar(1024)",
		"Type"			  	=> "Enum(array('wms','wfs','wmsUntiled'),'wms')",
		"DisplayPriority" 	=> "Int",		
		"Enabled"         	=> "Boolean",
		"Visible"         	=> "Boolean",
		"Queryable"			=> "Boolean",
		
		// temporarily added (will be removed)
		"ogc_name"			=> "Varchar(50)",		// layer name (ogc layer name/id)
		"ogc_map"			=> "Varchar(1024)",		// url to the map file on the server side
		"ogc_format"		=> "Enum(array('png','jpeg','png24','gif'),'png')",
		"ogc_transparent"	=> "Boolean"			// transparent overlay layer
	);
	
	static $has_one = array(
		'Map' => 'OLMapObject'
	);	
	
	static $field_labels = array(
		"Type"             => "OGC API",
		"ogc_name"         => "OGC Layer Name",
		"ogc_map"          => "Map-filename",
		"ogc_transparent"  => "Transparency",
		"Map.Title"        => "Map Name"
	);	
	
	static $summary_fields = array(
		'Title',
		'ogc_name',
		'Type',
		'Enabled',
		'Visible',
		'Queryable',
		'ogc_transparent',
		'Map.Title'
	 );

	static $defaults = array(
	    'DisplayPriority' => 50,
	    'Enabled' => true,
	    'Visible' => false,
	    'Queryable' => true,
	    'ogc_transparent' => true
	 );

	static $casting = array(
		'Enabled' => 'Boolean',
	);
	
	static $default_sort = "Title ASC";

	/**
	 * Overwrites SiteTree.getCMSFields.
	 *
	 * @return fieldset
	 */ 
	function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->removeFieldsFromTab("Root.Main", array(
			"Url","DisplayPriority","Enabled", "Visible", "Queryable","ogc_name","ogc_map", "ogc_transparent"
		));

		$LayerType = $fields->fieldByName("Root.Main.Type");
		$fields->removeFieldFromTab("Root.Main","Type");

		$fields->addFieldsToTab("Root.Main", 
			array(
				new LiteralField("DisLabel","<h2>Layer Settings</h2>"),
				// Display parameters
				new CompositeField( 
					new CompositeField( 
						new LiteralField("URLLabel","<h3>URL Server Settings</h3>"),
						new TextField("Url", "URL"),
						new TextField("ogc_map", "Map filename"),
						new LiteralField("MapLabel","<i>Optional: Path to UMN Mapserver Mapfile</i>")
					),
					new CompositeField( 
						new LiteralField("OGCLabel","<h3>Display Settings</h3>"),
						new NumericField("DisplayPriority", "Draw Priority"),
						new CheckboxField("Enabled", "Enabled"),
						new CheckboxField("Visible","Visible"),
						new CheckboxField("Queryable", "Queryable")
					),
					new CompositeField( 
						new LiteralField("OGCLabel","<h3>OGC Settings</h3>"),
						new TextField("ogc_name", "Layer Name"),
						new LiteralField("MapLabel","<i>(as defined in GetCapabilities)</i>"),
						$LayerType,
						new CheckboxField("ogc_transparent", "Transparency")

					)
				)
			)
		);
		return $fields;
	}
	
	/**
	 * Creates and returns a layer definition array which will be used to configure
	 * open layers on the JavaScript side.
	 *
	 * @return array
	 */
	function getConfigurationArray() {
		$config = array();
		
		$layerType = $this->getField('Type');
		
		$config['Type']        = $this->getField('Type');
		$config['Title']       = $this->getField('Title');
		$config['Url']         = $this->getField('Url');
		$config['Visible']     = $this->getField('Visible');
		$config['ogc_name']    = $this->getField('ogc_name');
		

		// create options element
		$options = array();
		$options['map']  = $this->getField("ogc_map");
				
		// handle layer type: WMS (tiled and untiled)
		if ($layerType == 'wms' || $layerType == 'wmsUntiled') {
			$options['SSID']        = $this->getField('ID');
			$options['layers']      = $this->getField("ogc_name");
			$options['transparent'] = $this->getField("ogc_transparent") ? "true": "false";
			$options['format']      = $this->getField('ogc_format');
		} else 
		// handle layer type: WFS
		if ($layerType == 'wfs' || $layerType == 'wfs_bound') {
			$options['SSID']     = $this->getField('ID');		
			$options['typename'] = $this->getField("ogc_name");	
		}

		$config['Options'] = $options;

		return $config;
	}	
		

	/**
	 * Wrapper class to handle OGC get-feature requests for all kind of 
	 * layer types.
	 *
	 * @throws OLLayer_Exception
	 *
	 * @param string type
	 * @param array param
	 *
	 * @return response
	 */
	function getFeatureInfo($featureID) {
		$Type = $this->getField('Type');
		
		$response = null;
		if ($Type == 'wms' || $Type == 'wmsUntiled') {
			$response = $this->sendWMSFeatureRequest($featureID);
		} else 
		if ($Type == 'wfs') {
			$response = $this->sendWFSFeatureRequest($featureID);
		} else {
			throw new OLLayer_Exception('Request type unknown');
		}
		return $response;
	}

	/**
	 *
	 */
	protected function sendWMSFeatureRequest($param){
		
		throw new OLLayer_Exception("Method not fully implemented");
		
		$staticParams = array(
			'REQUEST' => 'GetFeatureInfo', 
			'INFO_FORMAT' => 'application/vnd.ogc.gml', 
			'VERSION' => '1.1.1', 
			'TRANSPARENT' => 'true', 
			'STYLE' => '', 
			'EXCEPTIONS' => 'application/vnd.ogc.se_xml', 
			'FORMAT' => 'image/png',
			'SRS' => 'EPSG%3A4326'
		);
		//$vars = $data->getVars();
		$URLRequest = "?map=".$this->ogc_map."&";
		
		foreach($staticParams as $k => $v){
			
			$URLRequest .= $k.'='.$v.'&';
		}
		$URLRequest .= "LAYERS=".$this->ogc_name."&QUERY_LAYERS=".$this->ogc_name."&BBOX=".$param['BBOX'];
		$URLRequest .= "&x=".$param['x']."&y=".$param['y']."&WIDTH=".$param['WIDTH']."&HEIGHT=".$param['HEIGHT'];
		$URLRequest = trim($URLRequest,"&");
		$URLRequest = str_replace('RequestURL=','',$URLRequest);
		
		$request = new RestfulService($this->Url);
		$xml = $request->request($URLRequest);
		return $xml;
	}
	
	/**
	 *
	 */
	function sendWFSFeatureRequest($featureID){
		
		$featureID = Convert::raw2xml($featureID);
		
		$ogcFeatureId = $this->getField('ogc_name').".".$featureID;
		$url          = $this->getField('Url');
		$map          = $this->getField('ogc_map');
		$typename     = $this->getField('ogc_name');
		
		$requestString = "?map=".$map."&request=getfeature&service=WFS&version=1.0.0&typename=".$typename."&OUTPUTFORMAT=gml3&featureid=".$ogcFeatureId;

		// set OGC WFS request to WFS server
		$request = new RestfulService($url);
		
		// get XML response
		$xml = $request->request($requestString);
		
		// get just XML part of the response
		$myxml = $xml->getBody();
		
		return $myxml;
			
		
		//http://202.36.29.39/cgi-bin/mapserv?map=/srv/www/htdocs/mapdata/spittelr/stations.map&request=getfeature&service=wfs&version=1.0.0&typename=Beam_trawl&OUTPUTFORMAT=gml3&featureid=Beam_trawl.6
	}
}

/**
 * Customised exception class
 */
class OLLayer_Exception extends Exception {
}