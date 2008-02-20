/*
 * The Diffie-Hellman key exchange protocol.
 */

// Our prime number as a base for operations.
var dh_prime = '82818079787776757473727170696867666564636261605958575655545352515049484746454443424140393837363534333231302928272625242322212019181716151413121110987654321';

// g, a primitive root used as an exponent
// (2 and 5 are acceptable, but BigInt is faster with odd numbers)
var dh_g = '5';

/**
 * Generates a Diffie-Hellman private key
 * @return string(BigInt)
 */

function dh_gen_private()
{
  return EnanoMath.RandomInt(256);
}

/**
 * Calculates the public key from the private key
 * @param string(BigInt)
 * @return string(BigInt)
 */

function dh_gen_public(b)
{
  return EnanoMath.PowMod(dh_g, b, dh_prime);
}

/**
 * Calculates the shared secret.
 * @param string(BigInt) Our private key
 * @param string(BigInt) Remote party's public key
 * @return string(BigInt)
 */

function dh_gen_shared_secret(b, A)
{
  return EnanoMath.PowMod(A, b, dh_prime);
}

