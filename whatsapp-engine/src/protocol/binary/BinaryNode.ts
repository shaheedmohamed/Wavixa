/**
 * Wavixa WhatsApp Engine - Binary Protocol Data Models
 */

export interface BinaryNode {
  tag: string;
  attrs: Record<string, string>;
  content?: Buffer | BinaryNode[] | string;
}

export function getBinaryNodeChild(node: BinaryNode | undefined, tag: string): BinaryNode | undefined {
  if (node && Array.isArray(node.content)) {
    return node.content.find((child) => child.tag === tag);
  }
  return undefined;
}

export function getBinaryNodeChildren(node: BinaryNode | undefined, tag?: string): BinaryNode[] {
  if (!node || !Array.isArray(node.content)) {
    return [];
  }
  if (!tag) {
    return node.content;
  }
  return node.content.filter((child) => child.tag === tag);
}

export function getBinaryNodeChildBuffer(node: BinaryNode, tag: string): Buffer | undefined {
  const child = getBinaryNodeChild(node, tag);
  if (child && Buffer.isBuffer(child.content)) {
    return child.content;
  }
  return undefined;
}

export function getBinaryNodeChildString(node: BinaryNode, tag: string): string | undefined {
  const child = getBinaryNodeChild(node, tag);
  if (!child) return undefined;
  if (typeof child.content === 'string') return child.content;
  if (Buffer.isBuffer(child.content)) return child.content.toString('utf8');
  return undefined;
}
