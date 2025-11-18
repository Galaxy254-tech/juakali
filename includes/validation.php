<?php
class Validation {
    private $errors = [];

    public function validate($data, $rules) {
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? '';
            $this->applyRules($field, $value, $rule);
        }
        return empty($this->errors);
    }

    private function applyRules($field, $value, $rules) {
        $rules = explode('|', $rules);
        
        foreach ($rules as $rule) {
            if (strpos($rule, ':') !== false) {
                list($ruleName, $ruleValue) = explode(':', $rule);
                $this->$ruleName($field, $value, $ruleValue);
            } else {
                $this->$rule($field, $value);
            }
        }
    }

    private function required($field, $value) {
        if (empty(trim($value))) {
            $this->addError($field, ucfirst($field) . ' is required');
        }
    }

    private function email($field, $value) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Invalid email format');
        }
    }

    private function min($field, $value, $length) {
        if (strlen($value) < $length) {
            $this->addError($field, ucfirst($field) . ' must be at least ' . $length . ' characters');
        }
    }

    private function max($field, $value, $length) {
        if (strlen($value) > $length) {
            $this->addError($field, ucfirst($field) . ' must not exceed ' . $length . ' characters');
        }
    }

    private function numeric($field, $value) {
        if (!is_numeric($value)) {
            $this->addError($field, ucfirst($field) . ' must be numeric');
        }
    }

    private function phone($field, $value) {
        if (!preg_match('/^(\+254|0)[0-9]{9}$/', $value)) {
            $this->addError($field, 'Invalid phone number format');
        }
    }

    private function unique($field, $value, $table) {
        $db = new Database();
        $result = $db->query("SELECT * FROM $table WHERE $field = ?")->bind($value)->single();
        if ($result) {
            $this->addError($field, ucfirst($field) . ' already exists');
        }
    }

    private function addError($field, $message) {
        $this->errors[$field] = $message;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getError($field) {
        return $this->errors[$field] ?? '';
    }
}
?>
