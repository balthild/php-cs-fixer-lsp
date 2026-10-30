{
  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-26.05";
    flake-parts.url = "github:hercules-ci/flake-parts";
    flake-parts.inputs.nixpkgs-lib.follows = "nixpkgs";
  };

  outputs = inputs@{ flake-parts, nixpkgs, ... }:
    flake-parts.lib.mkFlake { inherit inputs; } {
      systems = nixpkgs.lib.systems.flakeExposed;

      perSystem = { pkgs, config, ... }: {
        packages = {
          php82 = pkgs.php82.override {
            ztsSupport = true;
          };

          php82-dev = config.packages.php82.buildEnv {
            extensions = { all, enabled }: with all; enabled ++ [
              xdebug
              opcache
              parallel
            ];
            extraConfig = ''
              xdebug.mode = debug
            '';
          };
        };

        devShells.default = pkgs.mkShell {
          buildInputs = [
            config.packages.php82-dev
            config.packages.php82.packages.composer
            config.packages.php82.packages.box
            pkgs.mago
          ];
        };
      };
    };
}
