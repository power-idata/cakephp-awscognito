<?php
namespace EvilCorp\AwsCognito\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Datasource\EntityInterface;
use Cake\Utility\Hash;

class ImportApiUsersBehavior extends Behavior
{

    public function validateMany(array $rows, $max_errors = false, array $options = [], $find_field = 'aws_cognito_username'): array
    {
        $_options = [
            'accessibleFields' => [
                'first_name'           => true,
                'last_name'            => true,
                'aws_cognito_username' => true,
                'email'                => true,
                'role'                 => true,
            ]
        ];

        $opts = array_merge($_options, $options);

        $entities = [];

        $duplicated_check = [
            'email'                => Hash::extract($rows, '{n}.email'),
            'aws_cognito_username' => Hash::extract($rows, '{n}.aws_cognito_username'),
        ];

        $errors_count = 0;

        foreach ($rows as $key => $row) {
            if($max_errors && $errors_count >= $max_errors) break;

            $find_conditions = [
                $find_field => $row[$find_field]
            ];

            if(is_a($row, EntityInterface::class, true)){
                $entity = $row;
            }else{
                $is_new = !$this->getTable()->exists($find_conditions);
                $entity = $is_new
                    ? $this->getTable()->newEntity($row, $opts)
                    : $this->getTable()->patchEntity(
                        $this->getTable()->find()->where($find_conditions)->firstOrFail(),
                        $row, $opts);
            }

            //check rules in db
            $this->getTable()->checkRules($entity);

            //check that the unique fields are also unique within the rows
            foreach (['email', 'aws_cognito_username'] as $field) {
                if(empty($row[$field])) continue;
                $duplicated_key = array_search($row[$field], $duplicated_check[$field]);
                if($duplicated_key !== false && $duplicated_key !== $key){
                    $entity->setError($field, ['duplicated' => __d('EvilCorp/AwsCognito', 'This field is duplicated')]);
                }
            }

            if($entity->getErrors()) $errors_count++;
            $entities[] = $entity;
        }

        return $entities;
    }

    public function csvDataToAssociativeArray(string $csv_data, array $fields = []): array
    {
        $fields = !empty($fields) ? $fields : [
            'aws_cognito_username',
            'email',
            'first_name',
            'last_name',
            'role'
        ];

        $rows = explode("\n", $csv_data);
        $parsed_rows = array_map('str_getcsv', $rows);

        array_walk($parsed_rows, function(&$row) use ($fields) {
            $filled_row  = $row + array_fill(0, count($fields), null);
            $cropped_row = array_slice($filled_row, 0, count($fields));
            $row         = array_combine($fields, $cropped_row);
        });

        return $parsed_rows;
    }
}