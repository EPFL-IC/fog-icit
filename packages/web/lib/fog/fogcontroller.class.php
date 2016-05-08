<?php
abstract class FOGController extends FOGBase {
    protected $data = array();
    protected $autoSave = false;
    protected $databaseTable = '';
    protected $databaseFields = array();
    protected $databaseFieldsRequired = array();
    protected $additionalFields = array();
    protected $databaseFieldsFlipped = array();
    protected $databaseFieldsToIgnore = array('createdBy','createdTime');
    protected $aliasedFields = array();
    protected $databaseFieldClassRelationships = array();
    protected $loadQueryTemplateSingle = "SELECT * FROM %s %s WHERE %s='%s' %s";
    protected $loadQueryTemplateMultiple = 'SELECT * FROM %s %s WHERE %s %s';
    protected $insertQueryTemplate = "INSERT INTO %s (%s) VALUES ('%s') ON DUPLICATE KEY UPDATE %s";
    protected $destroyQueryTemplate = "DELETE FROM %s WHERE %s='%s'";
    public function __construct($data = '') {
        parent::__construct();
        $this->databaseTable = trim($this->databaseTable);
        $this->databaseFields = array_filter(array_unique((array)$this->databaseFields));
        try {
            if (!isset($this->databaseTable)) throw new Exception(_('No database table defined for this class'));
            if (!count($this->databaseFields)) throw new Exception(_('No database fields defined for this class'));
            if (is_numeric($data) && (int) $data < 1) throw new Exception(_('Improper data passed'));
            $this->databaseFieldsFlipped = array_flip($this->databaseFields);
            if (is_numeric($data)) $this->set('id',$data)->load();
            else if (is_array($data)) $this->setQuery($data);
        } catch (Exception $e) {
            $this->error(_('Record not found, Error: %s'),array($e->getMessage()));
        }
        return $this;
    }
    public function __destruct() {
        if ($this->autoSave) $this->save();
        return false;
    }
    public function __toString() {
        $str = sprintf('%s ID: %s',get_class($this),$this->get('id'));
        if ($this->get('name')) $str = sprintf('%s %s: %s',$str,_('Name'),$this->get('name'));
        return (string)$str;
    }
    public function get($key = '') {
        $key = $this->key($key);
        if (!$key) return $this->data;
        if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) {
            unset($this->data[$key]);
            return false;
        }
        if (!$this->isLoaded($key)) $this->loadItem($key);
        if (!isset($this->data[$key])) return $this->data[$key] = '';
        if (is_object($this->data[$key])) {
            $this->info(sprintf('%s: %s, %s: %s',_('Returning value of key'),$key,_('Object'),$this->data[$key]->__toString()));
        } else if (is_array($this->data[$key])) {
            $this->info(sprintf('%s: %s',_('Returning array within key'),$key));
        } else {
            $this->info(sprintf('%s: %s, %s: %s',_('Returning value of key'),$key,_('Value'),$this->data[$key]));
        }
        return $this->data[$key];
        //$retVal = is_array($this->data[$key]) || is_string($this->data[$key]) ? array_map($convertEncoding,(array)$this->data[$key]) : array($this->data[$key]);
        //return count($retVal) === 1 ? array_shift($retVal) : $retVal;
    }
    public function set($key, $value) {
        try {
            $key = $this->key($key);
            if (!$key) throw new Exception(_('No key being requested'));
            else if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) {
                unset($this->data[$key]);
                throw new Exception(_('Invalid key being set'));
            } else if (!$this->isLoaded($key)) $this->loadItem($key);
            if (is_numeric($value) && $value < ($key == 'id' ? 1 : -1)) throw new Exception(_('Invalid numeric entry'));
            if (is_object($value)) {
                $this->info(sprintf('%s: %s %s: %s',_('Setting Key'),$key,_('Object'),$value->__toString()));
            } else if (is_array($value)) {
                $this->info(sprintf('%s: %s %s',_('Setting Key'),$key,_('Array of data')));
            } else {
                $this->info(sprintf('%s: %s %s: %s',_('Setting Key'),$key,_('Value'),$value));
            }
            $this->data[$key] = $value;
        } catch (Exception $e) {
            $this->debug(_('Set Failed: Key: %s, Value: %s, Error: %s'),array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function add($key, $value) {
        try {
            $key = $this->key($key);
            if (!$key) throw new Exception(_('No key being requested'));
            else if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) {
                unset($this->data[$key]);
                throw new Exception(_('Invalid key being added'));
            } else if (!$this->isLoaded($key)) $this->loadItem($key);
            if (is_object($value)) {
                $this->info(sprintf('%s: %s, %s: %s',_('Adding Key'),$key,_('Object'),$value->__toString()));
                $this->data[$key][] = $value;
            } else if (is_array($value)) {
                $this->info(sprintf('%s: %s %s',_('Adding Key'),$key,_('Array of data')));
                $this->data[$key][] = $value;
            } else {
                $value = $value;
                $this->info(sprintf('%s: %s %s: %s',_('Adding Key'),$key,_('Value'),$value));
                $this->data[$key][] = $value;
            }
        } catch (Exception $e) {
            $this->debug(_('Add Failed: Key: %s, Value: %s, Error: %s'),array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function remove($key, $value) {
        try {
            $key = $this->key($key);
            if (!$key) throw new Exception(_('No key being requested'));
            else if (!array_key_exists($key,(array)$this->databaseFields) && !array_key_exists($key,(array)$this->databaseFieldsFlipped) && !in_array($key,(array)$this->additionalFields)) {
                unset($this->data[$key]);
                throw new Exception(_('Invalid key being removed'));
            } else if (!$this->isLoaded($key)) $this->loadItem($key);
            if (!is_array($this->data[$key])) $this->data[$key] = array($this->data[$key]);
            $this->data[$key] = array_unique($this->data[$key]);
            $index = array_search($value,$this->data[$key]);
            $this->info(sprintf(_('Removing Key: %s, Value: %s'),$key, $value));
            unset($this->data[$key][$index]);
            $this->data[$key] = array_values(array_filter($this->data[$key]));
        } catch (Exception $e) {
            $this->debug(_('Remove Failed: Key: %s, Value: %s, Error: %s'),array($key, $value, $e->getMessage()));
        }
        return $this;
    }
    public function save() {
        $this->info(sprintf(_('Saving data for %s object'),get_class($this)));
        try {
            $insertKeys = $insertValues = $updateData = $fieldData = array();
            if (count($this->aliasedFields)) $this->array_remove($this->aliasedFields, $this->databaseFields);
            array_walk($this->databaseFields,function(&$field,&$name) use (&$insertKeys,&$insertValues,&$updateData) {
                $key = sprintf('`%s`',trim($field));
                if ($name == 'createdBy' && !$this->get($name)) $val = trim($_SESSION['FOG_USERNAME'] ? self::$DB->sanitize($_SESSION['FOG_USERNAME']) : 'fog');
                else if ($name == 'createdTime' && (!$this->get('createdTime') || !$this->validDate($this->get($name)))) $val = $this->formatTime('now','Y-m-d H:i:s');
                else $val = self::$DB->sanitize($this->get($name));
                if ($name == 'id' && (empty($val) || $val == null || $val == 0 || $val == false)) return;
                $insertKeys[] = $key;
                $insertValues[] = $val;
                $updateData[] = sprintf("%s='%s'",$key,$val);
                unset($key,$val,$field,$name);
            });
            $query = sprintf($this->insertQueryTemplate,
                $this->databaseTable,
                implode(',',(array)$insertKeys),
                implode("','",(array)$insertValues),
                implode(',',(array)$updateData)
            );
            if (!self::$DB->query($query)->fetch()->get()) throw new Exception(self::$DB->sqlerror());
            if (!$this->get('id')) $this->set('id',self::$DB->insert_id());
            if (!$this instanceof History) {
                if ($this->get('name')) $this->log(sprintf('%s ID: %s NAME: %s %s.',get_class($this),$this->get('id'),$this->get('name'),_('has been successfully updated')));
                else $this->log(sprintf('%s ID: %s %s.',get_class($this),$this->get('id'),_('has been successfully updated')));
            }
        } catch (Exception $e) {
            if (!$this instanceof History) {
                if ($this->get('name')) $this->log(sprintf('%s ID: %s NAME: %s %s. ERROR: %s',get_class($this),$this->get('id'),$this->get('name'),_('has failed to save'),$e->getMessage()));
                else $this->log(sprintf('%s ID: %s %s. ERROR: %s',get_class($this),$this->get('id'),_('has failed to save'),$e->getMessage()));
            }
            $this->debug(_('Database save failed: ID: %s, Error: %s'),array($this->data['id'],$e->getMessage()));
            return false;
        }
        return $this;
    }
    public function load($field = 'id') {
        $this->info(sprintf(_('Loading data to field %s'),$field));
        try {
            if (!is_array($field) && $field && !$this->get($field)) throw new Exception(_(sprintf(_('Operation Field not set: %s'),$field)));
            list($join, $where) = $this->buildQuery();
            if (!is_array($field)) $field = array($field);
            array_map(function(&$key) use ($join,$where) {
                $key = $this->key($key);
                if (!is_array($this->get($key))) {
                    $query = sprintf($this->loadQueryTemplateSingle,
                        $this->databaseTable,
                        $join,
                        $this->databaseFields[$key],
                        self::$DB->sanitize($this->get($key)),
                        count($where) ? ' AND '.implode(' AND ',$where) : ''
                    );
                } else {
                    $fields = $this->get($key);
                    $fieldData = array();
                    array_map(function(&$fieldValue) use ($key,&$fieldData) {
                        $fieldData[] = sprintf("`%s`.`%s`='%s'",$this->databaseTable,$this->databaseFields[$key],$fieldValue);
                        unset($fieldValue,$key);
                    },(array)$fields);
                    $query = sprintf($this->loadQueryTemplateMultiple,
                        $this->databaseTable,
                        $join,
                        implode(' OR ', $fieldData),
                        count($where) ? ' AND '.implode(' AND ',$where) : ''
                    );
                }
                $vals = array();
                if (!($vals = self::$DB->query($query)->fetch('','fetch_assoc')->get())) throw new Exception(self::$DB->sqlerror());
                $this->setQuery($vals);
                unset($vals,$key,$join,$where);
            },(array)$field);
        } catch (Exception $e) {
            $this->debug(_('Load failed: %s'),array($e->getMessage()));
        }
        return $this;
    }
    public function destroy($field = 'id') {
        $this->info(sprintf(_('Destroying data from field %s'),$field));
        try {
            if (!$this->get($field)) throw new Exception(sprintf(_('Operation Field not set: %s'),$field));
            if (!array_key_exists($field, $this->databaseFields) && !array_key_exists($field, $this->databaseFieldsFlipped)) throw new Exception(_('Invalid Operation Field set'));
            if (array_key_exists($field, $this->databaseFields)) $fieldToGet = $this->databaseFields[$field];
            $query = sprintf($this->destroyQueryTemplate,
                $this->databaseTable,
                $fieldToGet,
                self::$DB->sanitize($this->get($this->key($field)))
            );
            if (!self::$DB->query($query)->fetch()->get()) throw new Exception(_('Could not delete item'));
            if (!$this instanceof History) {
                if ($this->get('name')) $this->log(sprintf('%s ID: %s NAME: %s %s.',get_class($this),$this->get('id'),$this->get('name'),_('has been destroyed')));
                else $this->log(sprintf('%s ID: %s %s.',get_class($this),$this->get('id'),_('has been destroyed')));
            }
        } catch (Exception $e) {
            if (!$this instanceof History) {
                if ($this->get('name')) $this->log(sprintf('%s ID: %s NAME: %s %s. ERROR: %s',get_class($this),$this->get('id'),$this->get('name'),_('has failed to be destroyed'),$e->getMessage()));
                else $this->log(sprintf('%s ID: %s %s. ERROR: %s',get_class($this),$this->get('id'),_('has failed to be destroyed'),$e->getMessage()));
            }
            $this->debug(_('Destroy failed: %s'),array($e->getMessage()));
        }
        return $this;
    }
    protected function key(&$key) {
        if (!is_array($key)) {
            $key = trim($key);
            if (array_key_exists($key,$this->databaseFieldsFlipped)) $key = $this->databaseFieldsFlipped[$key];
            return $key;
        }
        return array_map(array($this,'key'),$key);
    }
    protected function loadItem($key) {
        if (!array_key_exists($key, $this->databaseFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !in_array($key, $this->additionalFields)) return $this;
        $methodCall = 'load'.ucfirst($key);
        if (method_exists($this,$methodCall)) $this->$methodCall();
        unset($methodCall);
        return $this;
    }
    public function isValid() {
        try {
            array_map(function(&$field) {
                if (!$this->get($field) === 0 && !$this->get($field)) throw new Exception(self::$foglang['RequiredDB']);
                unset($field);
            },(array)$this->databaseFieldsRequired);
            if (!$this->get('id')) throw new Exception(_('Invalid ID'));
            if (array_key_exists('name',(array)$this->databaseFields) && !$this->get('name')) throw new Exception(_(get_class($this).' no longer exists'));
        } catch (Exception $e) {
            $this->debug('isValid Failed: Error: %s',array($e->getMessage()));
            return false;
        }
        return true;
    }
    public function buildQuery($not = false, $compare = '=') {
        $join = array();
        $whereArrayAnd = array();
        $c = null;
        $whereInfo = function(&$value,&$field) use (&$whereArrayAnd,&$c,$not,$compare) {
            if (is_array($value)) $whereArrayAnd[] = sprintf("`%s`.`%s` IN ('%s')",$c->databaseTable,$field,implode("','",$value));
            else $whereArrayAnd[] = sprintf("`%s`.`%s` %s '%s'",$c->databaseTable,$c->databaseFields[$field],(preg_match('#%#',$value) ? 'LIKE' : $compare), $value);
            unset($value,$field);
        };
        $joinInfo = function(&$fields,&$class) use (&$join,&$whereArrayAnd,&$whereInfo,&$c) {
            $c = self::getClass($class);
            $join[] = sprintf(' LEFT OUTER JOIN `%s` ON `%s`.`%s`=`%s`.`%s` ',$c->databaseTable,$c->databaseTable,$c->databaseFields[$fields[0]],$this->databaseTable,$this->databaseFields[$fields[1]]);
            if ($fields[3]) array_walk($fields[3],$whereInfo);
            unset($class,$fields,$c);
        };
        array_walk($this->databaseFieldClassRelationships,$joinInfo);
        return array(implode((array)$join),$whereArrayAnd);
    }
    public function setQuery(&$queryData) {
        $classData = array_intersect_key((array)$queryData,(array)$this->databaseFieldsFlipped);
        if (count($classData) <= 0) $classData = array_intersect_key((array)$queryData,$this->databaseFields);
        else {
            array_walk($this->databaseFieldsFlipped,function(&$obj_key,&$db_key) use (&$classData) {
                $this->array_change_key($classData,$db_key,$obj_key);
                unset($obj_key,$db_key);
            });
        }
        $trimPlus = function(&$val,&$key) use (&$callback) {
            $val = htmlspecialchars($val,self::$service ? ENT_NOQUOTES : ENT_QUOTES,'utf-8');
            $val = $callback ? $callback(trim($val)) : trim($val);
        };
        $callback = self::$service ? false : 'addslashes';
        array_walk($classData,$trimPlus);
        $this->data = array_merge((array)$this->data,(array)$classData);
        array_walk($this->databaseFieldClassRelationships,function(&$fields,&$class) use (&$queryData) {
            $class = self::getClass($class);
            $leftover = array_intersect_key((array)$queryData,(array)$class->databaseFieldsFlipped);
            $this->set($fields[2],$class->setQuery($leftover));
            unset($fields,$class);
        });
        return $this;
    }
    public function getManager() {
        return self::getClass(sprintf('%sManager',get_class($this)));
    }
}
