/*
 * EnanoMath, an abstraction layer for big-integer (arbitrary precision)
 * mathematics.
 */

var EnanoMathLayers = {};

// EnanoMath layer: Leemon (frontend to BigInt library by Leemon Baird)

EnanoMathLayers.Leemon = {
  Base: 10,
  PowMod: function(a, b, c)
  {
    a = str2bigInt(a, this.Base);
    b = str2bigInt(b, this.Base);
    c = str2bigInt(c, this.Base);
    var result = powMod(a, b, c);
    result = bigInt2str(result, this.Base);
    return result;
  },
  RandomInt: function(bits)
  {
    var result = randBigInt(bits);
    return bigInt2str(result, this.Base);
  }
}

var EnanoMath = EnanoMathLayers.Leemon;

