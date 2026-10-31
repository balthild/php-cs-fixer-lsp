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

          # Box takes `php82` instead of the scope's `php`, so it silently resolves to the
          # plain pkgs.php82 and escapes the override above. Fixed upstream in PR #575757.
          # Delete this override when bumping nixpkgs past it.
          box = pkgs.php82Packages.box.override {
            php82 = config.packages.php82;
          };
        };

        devShells.default = pkgs.mkShell {
          buildInputs = [
            config.packages.php82-dev
            config.packages.php82.packages.composer
            config.packages.box
            pkgs.mago
          ];
        };
      };
    };
}
