declare module 'curve25519-js' {
  export function generateKeyPair(seed: Uint8Array): { public: Uint8Array; private: Uint8Array };
  export function sharedKey(secretKey: Uint8Array, publicKey: Uint8Array): Uint8Array;
  export function sign(secretKey: Uint8Array, msg: Uint8Array, opt_random?: Uint8Array): Uint8Array;
  export function verify(publicKey: Uint8Array, msg: Uint8Array, signature: Uint8Array): boolean;
  export function signMessage(secretKey: Uint8Array, msg: Uint8Array, opt_random?: Uint8Array): Uint8Array;
  export function openMessage(publicKey: Uint8Array, signedMsg: Uint8Array): Uint8Array | null;
}
