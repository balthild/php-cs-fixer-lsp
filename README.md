# PHP CS Fixer Language Server

This project provides a Language Server Protocol (LSP) implementation for PHP CS Fixer. Unlike other existing implementations, this one runs PHP CS Fixer continuously in the background, minimizing the initialization time overhead for each formatting request.

## Installation

Download manually from [releases](https://github.com/balthild/php-cs-fixer-lsp/releases), or install with Phive:

```bash
phive install balthild/php-cs-fixer-lsp
```

## Building from Source

Compile to a PHAR file:

```
box compile
```

The output will be located at `build` directory.

## Fun Fact

This project itself does not use PHP CS Fixer but [Mago](https://github.com/carthage-software/mago). If you don't need the fine-grained control of PHP CS Fixer, give Mago a try because it's super-fast!
