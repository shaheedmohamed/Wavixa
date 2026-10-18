/**
 * Wavixa WhatsApp Engine - Safety & Anti-Abuse Risk Engine
 * Computes dynamic risk score (0 - 100) and halts abusive automation loops.
 */

export type RiskLevel = 'SAFE' | 'LOW' | 'MEDIUM' | 'HIGH' | 'BLOCK_SEND';

export interface RiskEvaluation {
  score: number;
  level: RiskLevel;
  action: 'proceed' | 'throttle' | 'block';
  reasons: string[];
}

export interface SessionMetrics {
  totalSent: number;
  totalFailed: number;
  totalOptOuts: number;
  recentDuplicateCount: number;
  burstCountLastMinute: number;
}

export class RiskEngine {
  /**
   * Evaluate session risk based on current activity metrics
   */
  public static evaluate(metrics: SessionMetrics): RiskEvaluation {
    let score = 0;
    const reasons: string[] = [];

    // 1. Failure rate (Max 35 points)
    if (metrics.totalSent > 10) {
      const failureRate = metrics.totalFailed / metrics.totalSent;
      if (failureRate > 0.4) {
        score += 35;
        reasons.push('High failure rate (>40% unverified/failing numbers)');
      } else if (failureRate > 0.2) {
        score += 20;
        reasons.push('Moderate failure rate (>20%)');
      }
    }

    // 2. Opt-out rate (Max 25 points)
    if (metrics.totalSent > 10 && metrics.totalOptOuts > 0) {
      const optOutRate = metrics.totalOptOuts / metrics.totalSent;
      if (optOutRate > 0.1) {
        score += 25;
        reasons.push('High recipient opt-out rate (>10%)');
      } else if (optOutRate > 0.05) {
        score += 15;
        reasons.push('Notable recipient opt-out rate (>5%)');
      }
    }

    // 3. Repeated / Duplicate content (Max 20 points)
    if (metrics.recentDuplicateCount > 15) {
      score += 20;
      reasons.push('Excessive identical messages detected without spintax');
    } else if (metrics.recentDuplicateCount > 5) {
      score += 10;
      reasons.push('Elevated duplicate content ratio');
    }

    // 4. Burst velocity (Max 20 points)
    if (metrics.burstCountLastMinute > 40) {
      score += 20;
      reasons.push('Velocity surge (>40 msg/min)');
    } else if (metrics.burstCountLastMinute > 25) {
      score += 10;
      reasons.push('Velocity elevated (>25 msg/min)');
    }

    let level: RiskLevel = 'SAFE';
    let action: 'proceed' | 'throttle' | 'block' = 'proceed';

    if (score >= 81) {
      level = 'BLOCK_SEND';
      action = 'block';
    } else if (score >= 61) {
      level = 'HIGH';
      action = 'throttle';
    } else if (score >= 41) {
      level = 'MEDIUM';
      action = 'throttle';
    } else if (score >= 21) {
      level = 'LOW';
      action = 'proceed';
    } else {
      level = 'SAFE';
      action = 'proceed';
    }

    return {
      score: Math.min(100, score),
      level,
      action,
      reasons,
    };
  }
}
