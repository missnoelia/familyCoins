<?php
declare(strict_types=1);

/**
 * Cálculo de saldos a partir de la regla central de Event::calcularImpacto().
 */
class Balance
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Devuelve los saldos del usuario y de su pareja.
     *
     * @return array{usuarios: array<int, array<string, mixed>>, saldo_conjunto: int}
     */
    public function getSaldos(int $usuarioId): array
    {
        $userModel = new User($this->db);
        $coupleModel = new Couple($this->db);
        $eventModel = new Event($this->db);
        $rechargeModel = new Recharge($this->db);

        $usuario = $userModel->getById($usuarioId);

        if ($usuario === null) {
            throw new ApiException('Usuario no encontrado.', 404);
        }

        $pareja = $coupleModel->getPartner($usuarioId);
        $miembros = [$usuario];

        if ($pareja !== null) {
            $parejaCompleta = $userModel->getById((int) $pareja['id']);

            if ($parejaCompleta !== null) {
                $miembros[] = $parejaCompleta;
            }
        }

        $eventos = $eventModel->getAll($usuarioId);
        $recargas = $rechargeModel->getAll($usuarioId);

        $saldos = [];

        foreach ($miembros as $miembro) {
            $id = (int) $miembro['id'];
            $saldoInicial = $miembro['saldo_inicial'] === null ? 0 : (int) $miembro['saldo_inicial'];
            $totalRecargas = 0;

            foreach ($recargas as $recarga) {
                if ((int) $recarga['usuario_id'] === $id) {
                    $totalRecargas += (int) $recarga['importe'];
                }
            }

            $totalResta = 0;
            $totalSuma = 0;

            foreach ($eventos as $evento) {
                $impacto = $eventModel->calcularImpacto($id, $evento);

                if ($impacto < 0) {
                    $totalResta += -$impacto;
                } elseif ($impacto > 0) {
                    $totalSuma += $impacto;
                }
            }

            $saldos[] = [
                'usuario_id' => $id,
                'nombre' => $miembro['nombre'],
                'saldo_inicial' => $miembro['saldo_inicial'] === null ? null : $saldoInicial,
                'total_recargas' => $totalRecargas,
                'total_resta' => $totalResta,
                'total_suma' => $totalSuma,
                'saldo' => $saldoInicial + $totalRecargas + $totalSuma - $totalResta,
            ];
        }

        return [
            'usuarios' => $saldos,
            'saldo_conjunto' => array_sum(array_column($saldos, 'saldo')),
        ];
    }

    /**
     * Devuelve el saldo de un usuario concreto.
     */
    public function getSaldoUsuario(int $usuarioId): int
    {
        $resultado = $this->getSaldos($usuarioId);

        foreach ($resultado['usuarios'] as $saldo) {
            if ((int) $saldo['usuario_id'] === $usuarioId) {
                return (int) $saldo['saldo'];
            }
        }

        return 0;
    }
}
