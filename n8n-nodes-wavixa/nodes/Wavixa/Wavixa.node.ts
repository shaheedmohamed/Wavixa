import { IExecuteFunctions } from 'n8n-core';
import {
    INodeExecutionData,
    INodeType,
    INodeTypeDescription,
    NodeOperationError,
} from 'n8n-workflow';

export class Wavixa implements INodeType {
    description: INodeTypeDescription = {
        displayName: 'Wavixa',
        name: 'wavixa',
        icon: 'file:wavixa.svg',
        group: ['transform'],
        version: 1,
        subtitle: '={{$parameter["operation"]}}',
        description: 'Send and receive WhatsApp messages via Wavixa',
        defaults: {
            name: 'Wavixa',
        },
        inputs: ['main'],
        outputs: ['main'],
        credentials: [
            {
                name: 'wavixaApi',
                required: true,
            },
        ],
        properties: [
            {
                displayName: 'Operation',
                name: 'operation',
                type: 'options',
                noDataExpression: true,
                options: [
                    {
                        name: 'Send WhatsApp Message',
                        value: 'sendMessage',
                        action: 'Send WhatsApp message',
                    },
                    {
                        name: 'Send OTP',
                        value: 'sendOtp',
                        action: 'Send OTP',
                    },
                    {
                        name: 'Send Image',
                        value: 'sendImage',
                        action: 'Send image',
                    },
                    {
                        name: 'Send File',
                        value: 'sendFile',
                        action: 'Send file',
                    },
                    {
                        name: 'Get Message Status',
                        value: 'getMessageStatus',
                        action: 'Get message status',
                    },
                    {
                        name: 'Get Session Status',
                        value: 'getSessionStatus',
                        action: 'Get session status',
                    },
                    {
                        name: 'Get Balance',
                        value: 'getBalance',
                        action: 'Get balance',
                    },
                ],
                default: 'sendMessage',
            },
            {
                displayName: 'Session ID',
                name: 'sessionId',
                type: 'number',
                default: 0,
                displayOptions: {
                    show: {
                        operation: ['sendMessage', 'sendOtp', 'sendImage', 'sendFile'],
                    },
                },
                description: 'Optional specific WhatsApp session. Leave 0 for any connected session.',
            },
            {
                displayName: 'Phone Number',
                name: 'to',
                type: 'string',
                default: '',
                placeholder: '+201234567890',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['sendMessage', 'sendOtp', 'sendImage', 'sendFile'],
                    },
                },
            },
            {
                displayName: 'Message Text',
                name: 'message',
                type: 'string',
                default: '',
                required: true,
                typeOptions: {
                    rows: 4,
                },
                displayOptions: {
                    show: {
                        operation: ['sendMessage'],
                    },
                },
            },
            {
                displayName: 'OTP Code',
                name: 'code',
                type: 'string',
                default: '',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['sendOtp'],
                    },
                },
            },
            {
                displayName: 'Image Base64 / URL',
                name: 'content',
                type: 'string',
                default: '',
                required: true,
                typeOptions: {
                    rows: 4,
                },
                displayOptions: {
                    show: {
                        operation: ['sendImage'],
                    },
                },
            },
            {
                displayName: 'Caption',
                name: 'caption',
                type: 'string',
                default: '',
                displayOptions: {
                    show: {
                        operation: ['sendImage'],
                    },
                },
            },
            {
                displayName: 'File Base64 / URL',
                name: 'content',
                type: 'string',
                default: '',
                required: true,
                typeOptions: {
                    rows: 4,
                },
                displayOptions: {
                    show: {
                        operation: ['sendFile'],
                    },
                },
            },
            {
                displayName: 'File Name',
                name: 'fileName',
                type: 'string',
                default: 'document.pdf',
                displayOptions: {
                    show: {
                        operation: ['sendFile'],
                    },
                },
            },
            {
                displayName: 'Message ID',
                name: 'messageId',
                type: 'number',
                default: 0,
                required: true,
                displayOptions: {
                    show: {
                        operation: ['getMessageStatus'],
                    },
                },
            },
        ],
    };

    async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
        const items = this.getInputData();
        const returnData: INodeExecutionData[] = [];

        const credentials = await this.getCredentials('wavixaApi');
        const baseUrl = (credentials.baseUrl as string).replace(/\/$/, '');
        const apiKey = credentials.apiKey as string;

        for (let i = 0; i < items.length; i++) {
            try {
                const operation = this.getNodeParameter('operation', i) as string;

                let endpoint = '';
                let method: 'GET' | 'POST' = 'POST';
                let body: Record<string, unknown> = {};

                switch (operation) {
                    case 'sendMessage':
                        endpoint = '/public/n8n/send-message';
                        body = {
                            to: this.getNodeParameter('to', i),
                            message: this.getNodeParameter('message', i),
                            session_id: this.getNodeParameter('sessionId', i) || undefined,
                        };
                        break;
                    case 'sendOtp':
                        endpoint = '/public/n8n/send-otp';
                        body = {
                            to: this.getNodeParameter('to', i),
                            code: this.getNodeParameter('code', i),
                            session_id: this.getNodeParameter('sessionId', i) || undefined,
                        };
                        break;
                    case 'sendImage':
                        endpoint = '/public/n8n/send-image';
                        body = {
                            to: this.getNodeParameter('to', i),
                            content: this.getNodeParameter('content', i),
                            caption: this.getNodeParameter('caption', i),
                            session_id: this.getNodeParameter('sessionId', i) || undefined,
                        };
                        break;
                    case 'sendFile':
                        endpoint = '/public/n8n/send-file';
                        body = {
                            to: this.getNodeParameter('to', i),
                            content: this.getNodeParameter('content', i),
                            file_name: this.getNodeParameter('fileName', i),
                            session_id: this.getNodeParameter('sessionId', i) || undefined,
                        };
                        break;
                    case 'getMessageStatus':
                        endpoint = `/public/n8n/message-status/${this.getNodeParameter('messageId', i)}`;
                        method = 'POST';
                        break;
                    case 'getSessionStatus':
                        endpoint = '/public/n8n/session-status';
                        method = 'GET';
                        break;
                    case 'getBalance':
                        endpoint = '/public/n8n/balance';
                        method = 'GET';
                        break;
                    default:
                        throw new NodeOperationError(this.getNode(), `Unknown operation: ${operation}`);
                }

                const response = await this.helpers.request({
                    method,
                    url: `${baseUrl}${endpoint}`,
                    headers: {
                        'X-API-Key': apiKey,
                        'Content-Type': 'application/json',
                    },
                    body: Object.keys(body).length ? JSON.stringify(body) : undefined,
                    json: true,
                });

                returnData.push({
                    json: response as any,
                });
            } catch (error: any) {
                if (this.continueOnFail()) {
                    returnData.push({
                        json: { error: error.message || String(error) },
                    });
                    continue;
                }
                throw error;
            }
        }

        return [returnData];
    }
}
