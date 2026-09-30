<?php
/**
 * IPV - Validación de datos de entrada
 * Uso:
 *   $v = new Validador($_POST);
 *   $v->requerido('nombre')->min(3)->max(100);
 *   $v->email('email');
 *   $v->password('password');
 *   if ($v->falla()) { print_r($v->errores()); }
 */

class Validador
{
    private array $datos;
    private array $errores = [];
    private array $camposValidados = [];

    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    /**
     * Devuelve el valor de un campo
     */
    public function valor(string $campo, $default = null)
    {
        $v = $this->datos[$campo] ?? $default;
        if (is_string($v)) {
            $v = trim($v);
        }
        return $v;
    }

    /**
     * Marca el campo como requerido
     */
    public function requerido(string $campo, ?string $etiqueta = null): self
    {
        $etiqueta = $etiqueta ?? $campo;
        $valor = $this->valor($campo);
        if ($valor === null || $valor === '' || $valor === []) {
            $this->errores[$campo][] = "El campo {$etiqueta} es obligatorio";
        }
        $this->camposValidados[] = $campo;
        return $this;
    }

    /**
     * Longitud mínima
     */
    public function min(string $campo, int $min, ?string $etiqueta = null): self
    {
        $etiqueta = $etiqueta ?? $campo;
        $valor = (string) $this->valor($campo, '');
        if ($valor !== '' && mb_strlen($valor) < $min) {
            $this->errores[$campo][] = "El campo {$etiqueta} debe tener al menos {$min} caracteres";
        }
        return $this;
    }

    /**
     * Longitud máxima
     */
    public function max(string $campo, int $max, ?string $etiqueta = null): self
    {
        $etiqueta = $etiqueta ?? $campo;
        $valor = (string) $this->valor($campo, '');
        if ($valor !== '' && mb_strlen($valor) > $max) {
            $this->errores[$campo][] = "El campo {$etiqueta} no puede exceder {$max} caracteres";
        }
        return $this;
    }

    /**
     * Valida formato de email
     */
    public function email(string $campo, ?string $etiqueta = null): self
    {
        $etiqueta = $etiqueta ?? 'email';
        $valor = (string) $this->valor($campo, '');
        if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $this->errores[$campo][] = "El {$etiqueta} no tiene un formato válido";
        }
        return $this;
    }

    /**
     * Valida número entero
     */
    public function entero(string $campo, ?string $etiqueta = null): self
    {
        $etiqueta = $etiqueta ?? $campo;
        $valor = $this->valor($campo, '');
        if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_INT)) {
            $this->errores[$campo][] = "El campo {$etiqueta} debe ser un número entero";
        }
        return $this;
    }

    /**
     * Valida número decimal
     */
    public function decimal(string $campo, ?string $etiqueta = null): self
    {
        $etiqueta = $etiqueta ?? $campo;
        $valor = $this->valor($campo, '');
        if ($valor !== '' && !is_numeric($valor)) {
            $this->errores[$campo][] = "El campo {$etiqueta} debe ser un número";
        }
        return $this;
    }

    /**
     * Valida que sea mayor o igual a un mínimo
     */
    public function mayorIgual(string $campo, $min, ?string $etiqueta = null): self
    {
        $etiqueta = $etiqueta ?? $campo;
        $valor = $this->valor($campo);
        if ($valor !== null && $valor !== '' && is_numeric($valor) && $valor < $min) {
            $this->errores[$campo][] = "El campo {$etiqueta} debe ser mayor o igual a {$min}";
        }
        return $this;
    }

    /**
     * Valida que sea uno de los valores permitidos
     */
    public function enLista(string $campo, array $permitidos, ?string $etiqueta = null): self
    {
        $etiqueta = $etiqueta ?? $campo;
        $valor = $this->valor($campo);
        if ($valor !== null && $valor !== '' && !in_array($valor, $permitidos, true)) {
            $this->errores[$campo][] = "El campo {$etiqueta} tiene un valor no permitido";
        }
        return $this;
    }

    /**
     * Valida contraseña según política del sistema
     */
    public function password(string $campo, ?string $etiqueta = null): self
    {
        require_once __DIR__ . '/Config.php';
        $etiqueta = $etiqueta ?? 'contraseña';
        $valor = (string) $this->valor($campo, '');
        if ($valor === '') {
            return $this;
        }

        $min = Config::int('pass_longitud_min', 8);
        $reqMayus = Config::bool('pass_req_mayuscula', true);
        $reqMinus = Config::bool('pass_req_minuscula', true);
        $reqNum   = Config::bool('pass_req_numero', true);
        $reqSim   = Config::bool('pass_req_simbolo', true);

        if (mb_strlen($valor) < $min) {
            $this->errores[$campo][] = "La {$etiqueta} debe tener al menos {$min} caracteres";
        }
        if ($reqMayus && !preg_match('/[A-Z]/', $valor)) {
            $this->errores[$campo][] = "La {$etiqueta} debe contener al menos una mayúscula";
        }
        if ($reqMinus && !preg_match('/[a-z]/', $valor)) {
            $this->errores[$campo][] = "La {$etiqueta} debe contener al menos una minúscula";
        }
        if ($reqNum && !preg_match('/[0-9]/', $valor)) {
            $this->errores[$campo][] = "La {$etiqueta} debe contener al menos un número";
        }
        if ($reqSim && !preg_match('/[!@#$%^&*()\-_=+\[\]{};:,.<>\/?~`\'"\\\\|]/', $valor)) {
            $this->errores[$campo][] = "La {$etiqueta} debe contener al menos un símbolo";
        }
        return $this;
    }

    /**
     * Valida que dos campos coincidan
     */
    public function iguales(string $campo1, string $campo2, string $mensaje = 'Los valores no coinciden'): self
    {
        $v1 = $this->valor($campo1);
        $v2 = $this->valor($campo2);
        if ($v1 !== $v2) {
            $this->errores[$campo2][] = $mensaje;
        }
        return $this;
    }

    /**
     * Valida fecha con formato dado
     */
    public function fecha(string $campo, string $formato = 'Y-m-d', ?string $etiqueta = null): self
    {
        $etiqueta = $etiqueta ?? $campo;
        $valor = $this->valor($campo, '');
        if ($valor !== '') {
            $dt = DateTime::createFromFormat($formato, $valor);
            if (!$dt || $dt->format($formato) !== $valor) {
                $this->errores[$campo][] = "El campo {$etiqueta} no tiene una fecha válida";
            }
        }
        return $this;
    }

    /**
     * ¿Falló la validación?
     */
    public function falla(): bool
    {
        return !empty($this->errores);
    }

    /**
     * ¿Pasó la validación?
     */
    public function pasa(): bool
    {
        return empty($this->errores);
    }

    /**
     * Devuelve todos los errores
     */
    public function errores(): array
    {
        return $this->errores;
    }

    /**
     * Devuelve los primeros errores por campo (string plano)
     */
    public function erroresPlanos(): array
    {
        $planos = [];
        foreach ($this->errores as $campo => $mensajes) {
            $planos[$campo] = $mensajes[0] ?? '';
        }
        return $planos;
    }

    /**
     * Devuelve los datos validados y limpios
     */
    public function datos(): array
    {
        $limpios = [];
        foreach ($this->camposValidados as $c) {
            $limpios[$c] = $this->valor($c);
        }
        return $limpios;
    }
}