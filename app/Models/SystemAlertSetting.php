<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemAlertSetting extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'he_thong_canh_bao';

    protected $fillable = [
        'cpuThreshold',
        'ramThreshold',
        'diskThreshold',
        'latencyThreshold',
        'errorRateThreshold',
        'emailAlertEnabled',
        'alertEmail',
        'telegramAlertEnabled',
        'telegramBotToken',
        'telegramChatId',
        'slackAlertEnabled',
        'slackWebhookUrl',
        'smsAlertEnabled',
        'smsPhoneNumber',
        'lastAlertSentAt',
    ];

    protected $casts = [
        'emailAlertEnabled' => 'boolean',
        'telegramAlertEnabled' => 'boolean',
        'slackAlertEnabled' => 'boolean',
        'smsAlertEnabled' => 'boolean',
        'cpuThreshold' => 'integer',
        'ramThreshold' => 'integer',
        'diskThreshold' => 'integer',
        'latencyThreshold' => 'integer',
        'errorRateThreshold' => 'integer',
    ];
}

