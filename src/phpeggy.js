"use strict";

exports.use = function(config, options) {
  config.passes.check.push(
    require("./passes/report-mbstring-incompatibility")
  );
  config.passes.generate = [
    require("./passes/generate-bytecode-php"),
    require("./passes/generate-php"),
  ];
  options.output = "source";
  if (!options.phpeggy) {
    options.phpeggy = {};
  }
  if (typeof options.phpeggy.parserNamespace === "undefined") {
    options.phpeggy.parserNamespace = "PHPeggy";
  }
  if (typeof options.phpeggy.parserClassName === "undefined") {
    options.phpeggy.parserClassName = "Parser";
  }
  if (typeof options.phpeggy.mbstringAllowed === "undefined") {
    options.phpeggy.mbstringAllowed = true;
  }
};
/*
 *   The MIT License (MIT)
 *
 *   Copyright (c) 2014-2022 The PHPeggy AUTHORS
 *
 *   Permission is hereby granted, free of charge, to any person obtaining a copy
 *   of this software and associated documentation files (the "Software"), to deal
 *   in the Software without restriction, including without limitation the rights
 *   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *   copies of the Software, and to permit persons to whom the Software is
 *   furnished to do so, subject to the following conditions:
 *
 *   The above copyright notice and this permission notice shall be included in all
 *   copies or substantial portions of the Software.
 *
 *   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *   SOFTWARE.
 */
