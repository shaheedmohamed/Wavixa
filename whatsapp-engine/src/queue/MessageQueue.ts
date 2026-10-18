import { SendMessagePayload } from '../message/MessageManager.js';

export interface QueueJob {
  id: string;
  sessionId: string;
  payload: SendMessagePayload;
  attempts: number;
  maxAttempts: number;
  createdAt: number;
  scheduledAt?: number;
}

export type JobHandler = (job: QueueJob) => Promise<string>; // returns messageId

export class MessageQueue {
  private queue: Map<string, QueueJob[]> = new Map(); // sessionId -> QueueJob[]
  private processing: Set<string> = new Set(); // sessionIds currently being processed
  private deadLetter: QueueJob[] = [];
  private handler: JobHandler | null = null;

  constructor() {}

  public setHandler(handler: JobHandler): void {
    this.handler = handler;
  }

  public async enqueue(
    sessionId: string,
    payload: SendMessagePayload,
    maxAttempts: number = 3,
    delayMs: number = 0,
  ): Promise<QueueJob> {
    const job: QueueJob = {
      id: `job_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`,
      sessionId,
      payload,
      attempts: 0,
      maxAttempts,
      createdAt: Date.now(),
      scheduledAt: delayMs > 0 ? Date.now() + delayMs : undefined,
    };

    let sessionQueue = this.queue.get(sessionId);
    if (!sessionQueue) {
      sessionQueue = [];
      this.queue.set(sessionId, sessionQueue);
    }
    sessionQueue.push(job);

    this.processNext(sessionId).catch(() => {});

    return job;
  }

  public getQueueDepth(sessionId: string): number {
    return this.queue.get(sessionId)?.length || 0;
  }

  public getDeadLetters(): QueueJob[] {
    return [...this.deadLetter];
  }

  private async processNext(sessionId: string): Promise<void> {
    if (this.processing.has(sessionId)) {
      return; // only one job per session at a time
    }

    const sessionQueue = this.queue.get(sessionId);
    if (!sessionQueue || sessionQueue.length === 0) {
      return;
    }

    const job = sessionQueue[0];
    if (!job) return;

    if (job.scheduledAt && job.scheduledAt > Date.now()) {
      const waitMs = job.scheduledAt - Date.now();
      setTimeout(() => this.processNext(sessionId), waitMs);
      return;
    }

    this.processing.add(sessionId);

    try {
      job.attempts += 1;
      if (this.handler) {
        await this.handler(job);
      }
      // Job successful, remove from queue
      sessionQueue.shift();
    } catch {
      if (job.attempts >= job.maxAttempts) {
        // Exceeded max attempts, move to dead-letter queue
        sessionQueue.shift();
        this.deadLetter.push(job);
      } else {
        // Delay before next attempt
        job.scheduledAt = Date.now() + 2000 * job.attempts;
      }
    } finally {
      this.processing.delete(sessionId);
      if (sessionQueue.length > 0) {
        setImmediate(() => this.processNext(sessionId));
      }
    }
  }
}
