/**
 * Wavixa WhatsApp Engine - Metrics Collector
 * Exposes Prometheus / OpenMetrics compatible metrics for scrapers.
 */

export class MetricsCollector {
  private static instance: MetricsCollector | null = null;

  private sessionsConnected = 0;
  private sessionsDisconnected = 0;
  private messagesSent = 0;
  private messagesReceived = 0;
  private messagesFailed = 0;
  private reconnectAttempts = 0;

  private constructor() {}

  public static getInstance(): MetricsCollector {
    if (!MetricsCollector.instance) {
      MetricsCollector.instance = new MetricsCollector();
    }
    return MetricsCollector.instance;
  }

  public incConnected(): void {
    this.sessionsConnected++;
  }

  public incDisconnected(): void {
    this.sessionsDisconnected++;
  }

  public incSent(): void {
    this.messagesSent++;
  }

  public incReceived(): void {
    this.messagesReceived++;
  }

  public incFailed(): void {
    this.messagesFailed++;
  }

  public incReconnect(): void {
    this.reconnectAttempts++;
  }

  /**
   * Produce standard OpenMetrics / Prometheus plaintext representation
   */
  public toPrometheus(): string {
    const mem = process.memoryUsage();
    const lines = [
      '# HELP wavixa_sessions_connected_total Total connected WhatsApp sessions',
      '# TYPE wavixa_sessions_connected_total counter',
      `wavixa_sessions_connected_total ${this.sessionsConnected}`,
      '',
      '# HELP wavixa_messages_sent_total Total messages dispatched',
      '# TYPE wavixa_messages_sent_total counter',
      `wavixa_messages_sent_total ${this.messagesSent}`,
      '',
      '# HELP wavixa_messages_received_total Total messages received',
      '# TYPE wavixa_messages_received_total counter',
      `wavixa_messages_received_total ${this.messagesReceived}`,
      '',
      '# HELP wavixa_messages_failed_total Total failed messages',
      '# TYPE wavixa_messages_failed_total counter',
      `wavixa_messages_failed_total ${this.messagesFailed}`,
      '',
      '# HELP wavixa_reconnect_attempts_total Total reconnect attempts',
      '# TYPE wavixa_reconnect_attempts_total counter',
      `wavixa_reconnect_attempts_total ${this.reconnectAttempts}`,
      '',
      '# HELP process_resident_memory_bytes Resident memory size in bytes',
      '# TYPE process_resident_memory_bytes gauge',
      `process_resident_memory_bytes ${mem.rss}`,
      '',
      '# HELP process_heap_used_bytes Process heap memory used in bytes',
      '# TYPE process_heap_used_bytes gauge',
      `process_heap_used_bytes ${mem.heapUsed}`,
    ];

    return lines.join('\n') + '\n';
  }
}
