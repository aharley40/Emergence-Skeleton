<?php

class CMS_Page extends CMS_Content
{
    // ActiveRecord configuration
    static public $defaultClass = __CLASS__;
    static public $singularNoun = 'page';
    static public $pluralNoun = 'pages';
    
    static public $fields = array(
        'LayoutClass' => array(
            'type' => 'enum'
            ,'values' => array('OneColumn')
            ,'default' => 'OneColumn'
        )
        ,'LayoutConfig'  => 'serialized'
    );
    
    
    static public function getAllPublishedByContextObject(ActiveRecord $Context, $options = array())
    {
		$options = MICS::prepareOptions($options, array(
			'conditions' => array()
		));
		
		$options['conditions']['Class'] = __CLASS__;
    
	    return parent::getAllPublishedByContextObject($Context, $options);
    }

}

