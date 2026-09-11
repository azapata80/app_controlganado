<?php

function period_month_end(string $date): string {
    return (new DateTimeImmutable($date))->modify('last day of this month')->format('Y-m-d');
}

function assert_period_open(PDO $pdo,string $date): void {
    $monthEnd=period_month_end($date);
    try{$stmt=$pdo->prepare("SELECT COUNT(*) FROM monthly_closings WHERE month_end=? AND status='CLOSED'");$stmt->execute([$monthEnd]);}
    catch(PDOException $e){return;}// Compatibilidad durante la aplicación de la migración 005.
    if((int)$stmt->fetchColumn()>0)throw new RuntimeException('El período está cerrado. Reábralo antes de registrar o modificar información.');
}

function assert_rows_in_open_period(PDO $pdo,array $rows,string $dateKey): void {
    $checked=[];
    foreach($rows as $row){$date=$row[$dateKey];$monthEnd=period_month_end($date);if(isset($checked[$monthEnd]))continue;assert_period_open($pdo,$date);$checked[$monthEnd]=true;}
}
