<?php

namespace app\models;

use lib\models\BaseModel;
use Yii;

/**
 * This is the model class for table "data_Permission".
 *
 * @property string $modified
 * @property string $name
 * @property string $description
 * @property integer $active
 */
class Permission extends BaseModel
{
  /**
   * @inheritdoc
   */
  public static function tableName()
  {
    return 'data_Permission';
  }

  /**
   * @inheritdoc
   */
  public function rules()
  {
    return [
      [['active'], 'integer'],
      [['namedId'], 'string', 'max' => 50],
      [['name'], 'string', 'max' => 100],
      [['description'], 'string', 'max' => 255],
      [['namedId'], 'unique'],
    ];
  }

  /**
   * @inheritdoc
   */
  public function attributeLabels()
  {
    return [
      'id' => Yii::t('app', 'ID'),
      'namedId' => Yii::t('app', 'Permission ID'),
      'created' => Yii::t('app', 'Created'),
      'modified' => Yii::t('app', 'Modified'),
      'name' => Yii::t('app', 'Name'),
      'description' => Yii::t('app', 'Description'),
      'active' => Yii::t('app', 'Active'),
    ];
  }

  public function getFormData()
  {
    return [
      'namedId' => [],
      'name'    => [],
      'description' => []
    ];
  }

  //-------------------------------------------------------------
  // Relations
  //-------------------------------------------------------------

  protected function getPermissionRoles()
  {
    return $this->hasMany(Permission_Role::className(), ['PermissionId' => 'id']);
  }

  protected function getRoles()
  {
    return $this->hasMany(Role::className(), ['id' => 'RoleId'])->via('permissionRoles');
  }
}
