<?php

require_once dirname(__FILE__) . '/../CommandImpl.php';

class FileMaker_Command_Edit_Implementation extends FileMaker_Command_Implementation {

    private $_fields = array();
    private $_modificationId = null;
    private $_deleteRelated;

    function FileMaker_Command_Edit_Implementation($fm, $layout, $recordId, $updateValues = array()) {
        FileMaker_Command_Implementation::FileMaker_Command_Implementation($fm, $layout);
        $this->_recordId = $recordId;
        $this->_deleteRelated = null;
        foreach ($updateValues as $fieldname => $value) {
            if (!is_array($value)) {
                $value = array(
                    $value
                );
            }
            $this->_fields[$fieldname] = $value;
        }
    }

    function & execute() {
        $params = $this->_getCommandParams();
        if (empty($this->_recordId)) {
            $error = new FileMaker_Error($this->_fm, 'Edit commands require a record id.');
            return $error;
        }
        if (!count($this->_fields)) {
            if ($this->_deleteRelated == null) {
                $error = new FileMaker_Error($this->_fm, 'There are no changes to make.');
                return $error;
            }
        }

        if ($this->_fm->getProperty('prevalidate')) {
            $layout = & $this->_fm->getLayout($this->_layout);
            $validationError = new FileMaker_Error_Validation($this->_fm);
            foreach ($layout->getFields() as $field => $infos) {
                if (isset($this->_fields[$field])) {
                    $infos = $this->_fields[$field];
                    foreach ($infos as $values) {
                        $validationError = $infos->validate($values);
                        if (FileMaker :: isError($validationError)) {
                            return $validationError;
                        }
                    }
                }
            }
        }

        $layout = & $this->_fm->getLayout($this->_layout);
        if (FileMaker :: isError($layout)) {
            return $layout;
        }
        $params['-edit'] = true;
        if ($this->_deleteRelated == null) {
            foreach ($this->_fields as $fieldname => $values) {
                if (strpos($fieldname, '.') !== false) {
                    list ($fieldname, $infos) = explode('.', $fieldname, 2);
                    $infos = '.' . $infos;
                } else {
                    $fieldname = $fieldname;
                    $infos = $layout->getField($fieldname);
                    if (FileMaker :: isError($infos)) {
                        return $infos;
                    }
                    if ($infos->isGlobal()) {
                        $infos = '.global';
                    } else {
                        $infos = '';
                    }
                }
                foreach ($values as $repetition => $value) {
                    $params[$fieldname . '(' . ($repetition + 1) . ')' . $infos] = $value;
                }
            }
        }
        if ($this->_deleteRelated != null) {
            $params['-delete.related'] = $this->_deleteRelated;
        }
        $params['-recid'] = $this->_recordId;
        if ($this->_modificationId) {
            $params['-modid'] = $this->_modificationId;
        }
        $result = $this->_fm->_execute($params);
        if (FileMaker :: isError($result)) {
            return $result;
        }
        return $this->_getResult($result);
    }

    function setField($field, $value, $repetition = 0) {
        $this->_fields[$field][$repetition] = $value;
        return $value;
    }

    function setFieldFromTimestamp($fieldname, $timestamp, $repetition = 0) {
        $layout = & $this->_fm->getLayout($this->_layout);
        if (FileMaker :: isError($layout)) {
            return $layout;
        }
        $field = & $layout->getField($fieldname);
        if (FileMaker :: isError($field)) {
            return $field;
        }
        switch ($field->getResult()) {
            case 'date' :
                return $this->setField($fieldname, date('m/d/Y', $timestamp), $repetition);
            case 'time' :
                return $this->setField($fieldname, date('H:i:s', $timestamp), $repetition);
            case 'timestamp' :
                return $this->setField($fieldname, date('m/d/Y H:i:s', $timestamp), $repetition);
        }
        return new FileMaker_Error($this->_fm, 'Only time, date, and timestamp fields can be set to the value of a timestamp.');
    }

    function setModificationId($modificationId) {
        $this->_modificationId = $modificationId;
    }

    function _setdeleteRelated($relatedRecordId) {
        $this->_deleteRelated = $relatedRecordId;
    }

}
