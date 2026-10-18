import {
    IWebhookFunctions,
    IWebhookResponseData,
    INodeType,
    INodeTypeDescription,
    IHttpRequestMethods,
} from 'n8n-workflow';

export class WavixaTrigger implements INodeType {
    description: INodeTypeDescription = {
        displayName: 'Wavixa Trigger',
        name: 'wavixaTrigger',
        icon: 'file:wavixa.svg',
        group: ['trigger'],
        version: 1,
        description: 'Starts a workflow when Wavixa events occur',
        defaults: {
            name: 'Wavixa Trigger',
        },
        inputs: [],
        outputs: ['main'],
        webhooks: [
            {
                name: 'default',
                httpMethod: 'POST' as IHttpRequestMethods,
                isFullPath: true,
                responseCode: '200',
                path: '={{$webhookUri}}',
                restartWebhook: true,
            },
        ],
        properties: [
            {
                displayName: 'Events',
                name: 'events',
                type: 'multiOptions',
                options: [
                    { name: 'Message Received', value: 'message.received' },
                    { name: 'Message Sent', value: 'message.sent' },
                    { name: 'Message Delivered', value: 'message.delivered' },
                    { name: 'Message Read', value: 'message.read' },
                    { name: 'Message Failed', value: 'message.failed' },
                    { name: 'Session Connected', value: 'session.connected' },
                    { name: 'Session Disconnected', value: 'session.disconnected' },
                    { name: 'Session Expired', value: 'session.expired' },
                ],
                default: [],
                description: 'Which Wavixa events to listen for',
            },
            {
                displayName: 'Secret',
                name: 'secret',
                type: 'string',
                typeOptions: { password: true },
                default: '',
                description: 'Webhook secret used to verify Wavixa signature',
            },
        ],
    };

    async webhook(this: IWebhookFunctions): Promise<IWebhookResponseData> {
        const bodyData = this.getBodyData();
        const req = this.getRequestObject();
        const secret = this.getNodeParameter('secret', '') as string;
        const events = this.getNodeParameter('events', []) as string[];

        const signature = req.headers['x-webhook-signature'] as string;

        if (secret && signature) {
            const crypto = await import('crypto');
            const expected = crypto
                .createHmac('sha256', secret)
                .update(JSON.stringify(bodyData))
                .digest('hex');
            if (signature !== expected) {
                return {
                    webhookResponse: 'Invalid signature',
                    workflowData: [[]],
                };
            }
        }

        const event = (bodyData as any).event as string;

        if (events.length && !events.includes(event)) {
            return {
                webhookResponse: 'Event ignored',
                workflowData: [[]],
            };
        }

        return {
            workflowData: [
                this.helpers.returnJsonArray([bodyData as any]),
            ],
        };
    }
}
