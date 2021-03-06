<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\Campaign;
use App\Model\Entity\User;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Campaigns Model
 *
 * @property \App\Model\Table\TemplatesTable&\Cake\ORM\Association\BelongsTo $Templates
 * @property \App\Model\Table\LogsTable&\Cake\ORM\Association\HasMany $Logs
 * @property \App\Model\Table\MailingListsTable&\Cake\ORM\Association\BelongsToMany $MailingLists
 *
 * @method \App\Model\Entity\Campaign get($primaryKey, $options = [])
 * @method \App\Model\Entity\Campaign newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Campaign[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Campaign|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Campaign saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Campaign patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Campaign[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Campaign findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class CampaignsTable extends Table
{
    const STATUS_NEW = 'new';
    const STATUS_IN_PROGRESS = 'in-progress';
    const STATUS_COMPLETED = 'completed';
    const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
    ];

    use MailerAwareTrait;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('campaigns');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Templates')
            ->setForeignKey('template_id')
            ->setConditions(['Templates.active' => true]);
        $this->hasMany('Logs', [
            'foreignKey' => 'campaign_id',
        ]);
        $this->belongsToMany('MailingLists', [
            'foreignKey' => 'campaign_id',
            'targetForeignKey' => 'mailing_list_id',
            'joinTable' => 'campaigns_mailing_lists',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('status')
            ->maxLength('status', 255)
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        $invalidMsg = __(
            'Invalid status, please use {0}',
            \Cake\Utility\Text::toList(static::STATUSES, __('or'))
        );
        $validator
            ->inList('status', static::STATUSES, $invalidMsg);

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['template_id'], 'Templates'));

        return $rules;
    }

    public function send($id): bool
    {
        $campaign = $this->get($id, [
            'contain' => ['Templates', 'MailingLists.Users'],
        ]);
        // NOTE: this query should be improved, left as a simple example
        foreach ($campaign->mailing_lists as $mailing_list) {
            foreach ($mailing_list->users as $user) {
                $this->emailMerge($campaign, $user);
            }
        }

        return true;
    }

    public function emailMerge(Campaign $campaign, User $user): void
    {
        $subjectTemplate = $campaign->template['subject'];
        $bodyTemplate = $campaign->template['body'];
        debug($this->getMailer('Campaign')->send('merge', [$user, $subjectTemplate, $bodyTemplate]));
    }
}
