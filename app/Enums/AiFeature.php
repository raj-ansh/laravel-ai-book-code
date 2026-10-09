<?php

namespace App\Enums;

/**
 * The AI features a user can be allowed to use. Plans in
 * config/acme.php list which features each plan includes.
 */
enum AiFeature: string
{
    case SupportAssistant = 'support_assistant';
    case AccountAssistant = 'account_assistant';
    case KnowledgeSearch = 'knowledge_search';
    case DocumentIntake = 'document_intake';
    case Analytics = 'analytics';
}
