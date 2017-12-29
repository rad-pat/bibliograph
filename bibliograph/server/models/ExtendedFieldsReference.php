<?php

namespace app\models;

use Yii;
use app\models\Reference;

/**
 * This is the model class for table "database3_data_Reference".
 * @inheritdoc
 * @property string $_category
 * @property string $_owner
 * @property string $_source
 * @property string $_sponsor
 * @property string $_date_ordered
 * @property string $_date_received
 * @property string $_date_reimbursement_requested
 * @property string $_inventory
 */
class ExtendedFieldsReference extends Reference
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'database3_data_Reference';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
      return array_merge(
        parent::rules(),
        [
          [['_date_ordered', '_date_received', '_date_reimbursement_requested'], 'safe'],
          [['_owner', '_sponsor', '_inventory'], 'string', 'max' => 50],
          [['_source'], 'string', 'max' => 255],
          [['_category'], 'string', 'max' => 100]
        ]
      );
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
      return array_merge(
        parent::rules(),
        [ 
          '_category' => Yii::t('app', 'Category'),
          '_owner' => Yii::t('app', 'Owner'),
          '_source' => Yii::t('app', 'Source'),
          '_sponsor' => Yii::t('app', 'Sponsor'),
          '_date_ordered' => Yii::t('app', 'Date Ordered'),
          '_date_received' => Yii::t('app', 'Date Received'),
          '_date_reimbursement_requested' => Yii::t('app', 'Date Reimbursement Requested'),
          '_inventory' => Yii::t('app', 'Inventory'),
        ]
      );
    }
}
