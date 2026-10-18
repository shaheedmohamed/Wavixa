import {
    ICredentialType,
    INodeProperties,
} from 'n8n-workflow';

export class WavixaApi implements ICredentialType {
    name = 'wavixaApi';
    displayName = 'Wavixa API';
    documentationUrl = 'https://wavixa.com/api-docs';
    properties: INodeProperties[] = [
        {
            displayName: 'Base URL',
            name: 'baseUrl',
            type: 'string',
            default: 'https://wavixa.com/api',
            placeholder: 'https://wavixa.com/api',
            required: true,
        },
        {
            displayName: 'API Key',
            name: 'apiKey',
            type: 'string',
            typeOptions: { password: true },
            default: '',
            required: true,
            description: 'Your Wavixa API key from the API Keys page',
        },
    ];
    authenticate = {
        type: 'genericCredentialType' as const,
        properties: {
            headers: {
                'X-API-Key': '={{$credentials.apiKey}}',
            },
        },
    };
}
