<?php

namespace Emergence\DAV;

class Collection extends \SiteCollection implements \Sabre\DAV\ICollection
{
    static public $autoCreate = true;
    static public $fileClass = '\Emergence\DAV\File';

    function __construct($handle, $record = null)
    {
       try {
            parent::__construct($handle, $record);
       } catch(Exception $e) {
           throw new \Sabre\DAV\Exception\FileNotFound($e->getMessage());
       }
    }

    // localize file creation
    public function createFile($path, $data = null, $ancestorID = NULL)
    {
        if ($this->Site != "Local") {
            throw new \Sabre\DAV\Exception\Forbidden('New files cannot be created under _parent');
            //return $this->getLocalizedCollection()->createFile($path, $data);
        }

        return parent::createFile($path, $data, $ancestorID);
    }


    public function delete()
    {
        return parent::delete();
    }

    function getChild($handle, $record = null)
    {
        if ($child = parent::getChild($handle, $record)) {
            return $child;
        } else {
            throw new \Sabre\DAV\Exception\FileNotFound('The file with name: ' . $handle . ' could not be found');
        }
    }

    public function childExists($name)
    {
        try {
            $this->getChild($name);
            return true;
        } catch(\Sabre\DAV\Exception\FileNotFound $e) {
            return false;
        }
    }
}