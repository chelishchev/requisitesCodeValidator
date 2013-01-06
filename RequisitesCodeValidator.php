<?php
/**
 * Валидатор, проверяющий банковские реквизиты
 */
class RequisitesCodeValidator extends CValidator
{
    /**
     * @var $type= - сюда передаётся тип реквизита, которвый нужно валидировать
     * default $type = "inn" - ИНН
     * "snils" - страховое свидетельство пенсионного фонда
     * "okpo" - ОКПО
     * "kBank" - корреспондентский счет
     * "rBank" - расчетный банковский счет
     * "ogrn" - ОГРН (ОГРНИП)
     * @var $bik - банковский идентификационный код. Необходимо указывать при валидации
     * расчетного и корреспондентского счетов.
     * default $bik = null;
     */
    public $type = "inn10";

    public $bik = null;

    /**
     * @var boolean whether the attribute value can be null or empty. Defaults to true,
     * meaning that if the attribute is empty, it is considered valid.
     */
    public $allowEmpty = true;

    //здесь определяем какой именно реквизит надо валидировать и раскидываем по вспомогательным методам
    protected function validateAttribute($object, $attribute)
    {
        $value = $object->$attribute; //если ничего не указано
        if ($this->allowEmpty && $this->isEmpty($value))
        {
            return;
        }
        elseif ($this->isEmpty($value))
        {
            $msg = Yii::t('validator', 'Значение реквизита не указано');
        }

        switch ($this->type) //смотрим, что за реквизит нам необходимо валидировать
        {
            case "inn" : //если это ИНН
                if (strlen($value) <= 10)
                {
                    $msg = $this->validateInn10($value);
                }
                elseif (strlen($value) >= 12)
                {
                    $msg = $this->validateInn12($value);
                }
                else
                {
                    $msg = Yii::t('validator', 'Длина ИНН должна быть 10 или 12 символов');
                }
                break;
            case "snils" : //если это страховое свидетельство пенсионного фонда
                $msg = $this->validateSnils(str_replace(array('-', ' '), "", $value));
                break;
            case "okpo" :
                $msg = $this->validateOkpo($value);
                break;
            case "kBank" : //чтобы избежать повторяющегося кода, заточим один метод для проверки обоих счетов
                $bik = $object->{$this->bik};
                if ($this->isEmpty($bik))
                {
                    $msg = Yii::t('validator', 'Значение БИК не указано');
                    break;
                }
                if (strlen($value) < 20) //если число символов меньше 20, то валидацию не проходит
                {
                    $msg = Yii::t('validator', 'Введено слишком малое количество символов');
                    break;
                }
                if (strlen($value) > 20) //если число символов больше 20, то валидация не успешна
                {
                    $msg = Yii::t('validator', 'Введено слишком большое количество символов');
                    break;
                }

                $msg = $this->validateBankAccount("0" . substr($bik, 4, 2) . $value);
                if ($msg)
                {
                    $msg = $msg . Yii::t('validator', "Введен некорректный номер корреспондентского счета.");
                }
                break;
            case "rBank" :
                $bik = $object->{$this->bik};
                if ($this->isEmpty($bik))
                {
                    $msg = Yii::t('validator', 'Значение БИК не указано');
                    break;
                }
                if (strlen($value) < 20) //если число символов меньше 20, то валидацию не проходит
                {
                    $msg = Yii::t('validator', 'Введено слишком малое количество символов');
                    break;
                }
                if (strlen($value) > 20) //если число символов больше 20, то валидация не успешна
                {
                    $msg = Yii::t('validator', 'Введено слишком большое количество символов');
                    break;
                }

                $msg = $this->validateBankAccount(substr($bik, 6, 3) . $value);
                if ($msg)
                {
                    $msg = $msg . Yii::t('validator', "Введен некорректный номер расчетного счета.");
                }
                break;
            case "ogrn" :
                $msg = $this->validateOgrn($value);
                break;
        }

        if ($msg)
        {
            $msg = $this->message !== null ? $this->message : $msg;
            $this->addError($object, $attribute, $msg);
        }

    }


    /**
     * @author Evgeniy Chernishev <EvgeniyRRU@gmail.com>
     * Метод выполняет проверку 10-значного ИНН по определённому
     * стандартному алгоритму
     * @param $value - значение 10-значного ИНН
     * @return string $msg - в случае успеха ничего не возвращает
     * в случае ошибки возвращает сообщение об ошибке
     */
    protected function validateInn10($value)
    {
        if (!is_numeric($value))
        {
            return $msg = Yii::t('validator', 'Ошибка. Введены неверные символы');
        }

        if (strlen($value) < 10) //если число символов меньше 10, то валидацию не проходит
        {
            $msg = Yii::t('validator', 'Введено слишком малое количество символов');
            return $msg;
        }

        //далее идёт логика
        $weightFactor = array(2, 4, 10, 3, 5, 9, 4, 6, 8, 0); //весовые коэффициенты
        $i            = 0;
        $checkSum     = 0;

        while ($i <= 9) //определяем контрольную сумму
        {
            $checkSum = $checkSum + substr($value, $i, 1) * $weightFactor[$i];
            $i++;
        }
        $refValue = $checkSum % 11; //определяем контрольное число
        if ($refValue > 9)
        {
            $refValue = $refValue % 10;
        }
        if (substr($value, 9, 1) == $refValue) //в этом самом выражении осуществляется проверка
        {
            return;
        }
        else
        {
            return $msg = Yii::t('validate', "Ошибка. ИНН неверный.");
        }
    }


    /**
     * @author Evgeniy Chernishev <EvgeniyRRU@gmail.com>
     * Метод выполняет проверку 12-значного ИНН по определённому
     * стандартному алгоритму
     * @param $value - значение 12-значного ИНН
     * @return string $msg - в случае успеха ничего не возвращает
     * в случае ошибки возвращает сообщение об ошибке
     */
    protected function validateInn12($value)
    {
        if (!is_numeric($value))
        {
            return $msg = Yii::t('validator', 'Ошибка. Введены неверные символы');
        }

        if (strlen($value) > 12) //если число символов больше 12, то валидация не успешна
        {
            $msg = Yii::t('validator', 'Введено слишком большое количество символов');
            return $msg;
        }

        //основная логика

        $weightFactor = array(3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8, 0); //массив весовых коэффициентов
        $i            = 0;
        $checkSum1    = 0;

        while ($i <= 10) // определение первой контрольной суммы
        {
            $checkSum1 = $checkSum1 + substr($value, $i, 1) * $weightFactor[$i + 1];
            $i++;
        }

        $refValue1 = $checkSum1 % 11; //первое контрольное число

        if ($refValue1 > 9)
        {
            $refValue1 = $refValue1 % 10;
        }

        $i         = 0;
        $checkSum2 = 0;

        while ($i <= 11) //определение второй контрольной суммы
        {
            $checkSum2 = $checkSum2 + substr($value, $i, 1) * $weightFactor[$i];
            $i++;
        }
        $refValue2 = $checkSum2 % 11; //второе контрольное число

        if ($refValue2 > 9)
        {
            $refValue2 = $refValue2 % 10;
        }

        if ((substr($value, 10, 1) == $refValue1) && (substr($value, 11, 1) == $refValue2)
        ) //в этом самом выражении осуществляется проверка
        {
            return;
        }
        else
        {
            return $msg = Yii::t('validate', "Ошибка. ИНН неверный.");
        }

    }


    /**
     * @author Evgeniy Chernishev <EvgeniyRRU@gmail.com>
     * Метод выполняет проверку номера СНИЛС
     * (страхового свидетельства пенсионного стахования)
     * @param $value - значение 11-значного СНИЛС
     * @return string $msg - в случае успеха ничего не возвращает
     * в случае ошибки возвращает сообщение об ошибке
     */
    protected function validateSnils($value)
    {
        if (!is_numeric($value))
        {
            return $msg = Yii::t('validator', 'Ошибка. Введены неверные символы');
        }

        if (strlen($value) < 11) //если число символов меньше 12, то валидацию не проходит
        {
            $msg = Yii::t('validator', 'Введено слишком малое количество символов');
            return $msg;
        }
        if (strlen($value) > 11) //если число символов больше 12, то валидация не успешна
        {
            $msg = Yii::t('validator', 'Введено слишком большое количество символов');
            return $msg;
        }

        //основная логика

        $weightFactor = array(9, 8, 7, 6, 5, 4, 3, 2, 1); //весовые коэффициенты
        $i            = 0;
        $checkSum     = 0;
        while ($i <= 8) //определяем контрольную сумму
        {
            $checkSum = $checkSum + substr($value, $i, 1) * $weightFactor[$i];
            $i++;
        }
        $refValue = $checkSum % 101; //определяем контрольное число

        if (substr($value, 9, 2) == $refValue) //в этом самом выражении осуществляется проверка
        {
            return;
        }
        else
        {
            return $msg = Yii::t('validate', "Ошибка. СНИЛС неверный.");
        }
    }

    /**
     * @author Evgeniy Chernishev <EvgeniyRRU@gmail.com>
     * Метод выполняет проверку 8-значного ОКПО по определённому
     * стандартному алгоритму
     * @param $value - значение 8-значного ОКПО
     * @return string $msg - в случае успеха ничего не возвращает
     * в случае ошибки возвращает сообщение об ошибке
     */
    protected function validateOkpo($value)
    {
        if (!is_numeric($value))
        {
            return $msg = Yii::t('validator', 'Ошибка. Введены неверные символы');
        }

        if (strlen($value) < 8) //если число символов меньше 12, то валидацию не проходит
        {
            $msg = Yii::t('validator', 'Введено слишком малое количество символов');
            return $msg;
        }
        if (strlen($value) > 8) //если число символов больше 12, то валидация не успешна
        {
            $msg = Yii::t('validator', 'Введено слишком большое количество символов');
            return $msg;
        }

        $weightFactor1 = array(1, 2, 3, 4, 5, 6, 7); //весовые коэффициенты
        $weightFactor2 = array(3, 4, 5, 6, 7, 8, 9);

        $i         = 0;
        $checkSum1 = 0;
        while ($i <= 6)
        {
            $checkSum1 = $checkSum1 + substr($value, $i, 1) * $weightFactor1[$i]; //контрольные суммы
            $i++;
        }
        $refValue1 = $checkSum1 % 11; //контрольные значения

        $i         = 0;
        $checkSum2 = 0;
        while ($i <= 6)
        {
            $checkSum2 = $checkSum2 + substr($value, $i, 1) * $weightFactor2[$i]; //контрольные суммы
            $i++;
        }
        $refValue2 = $checkSum2 % 11; //контрольные значения

        if ($refValue1 > 9) //основная проверка
        {
            if (substr($value, 7, 1) == $refValue2)
            {
                return;
            }
            else
            {
                $msg = Yii::t('validator', 'Введен неверный ОКПО');
                return $msg;
            }
        }
        else
        {
            if (substr($value, 7, 1) == $refValue1)
            {
                return;
            }
            else
            {
                $msg = Yii::t('validator', 'Введен неверный ОКПО');
                return $msg;
            }
        }
    }

    /**
     * @author Evgeniy Chernishev <EvgeniyRRU@gmail.com>
     * Метод выполняет проверку 20-значного корр. или расчетного счета по определённому
     * стандартному алгоритму
     * @param $value - значение 20-значного корр. или расчетного счета
     * @return string $msg - в случае успеха ничего не возвращает
     * в случае ошибки возвращает сообщение об ошибке
     */
    protected function validateBankAccount($value)
    {
        if (!is_numeric($value))
        {
            return $msg = Yii::t('validator', 'Ошибка. Введены неверные символы');
        }
        //массив весовых коэффициентов
        $weightFactor = array(7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1);

        $i        = 0;
        $checkSum = 0;
        while ($i <= 22) //находим контрольную сумму
        {
            $checkSum = $checkSum + substr($value, $i, 1) * $weightFactor[$i];
            $i++;
        }
        $refValue = $checkSum % 10; //определяем контрольное число
        if ($refValue == 0) //основная проверка
        {
            return;
        }
        else
        {
            return $msg = Yii::t('validate', "Ошибка. ");
        }
    }


    /**
     * @author Evgeniy Chernishev <EvgeniyRRU@gmail.com>
     * Метод выполняет проверку 13-значного ОГРН или 15-значного ОГРНИП
     * стандартному алгоритму
     * @param $value - значение 13-значного ОГРН или 15-значного ОГРНИП
     * @return string $msg - в случае успеха ничего не возвращает
     * в случае ошибки возвращает сообщение об ошибке
     */
    protected function validateOgrn($value)
    {
        if (!is_numeric($value))
        {
            return $msg = Yii::t('validator', 'Ошибка. Введены неверные символы');
        }

        if (strlen($value) == 13)
        {
            $check        = substr($value, 0, 12); // просто написать % для определения остатка тут не получилось
            $checkValue1  = $check / 11; // видать php на больших числах считает остаток не точно.
            $checkValue   = $check - (floor($checkValue1)) * 11;
            $controlValue = substr($value, 12);
        }
        elseif (strlen($value) == 15)
        {
            $check        = substr($value, 0, 14);
            $checkValue1  = $check / 11;
            $checkValue   = $check - (floor($checkValue1)) * 11;
            $controlValue = substr($value, 14);
        }
        else
        {
            $msg = Yii::t('validator', 'Ошибка. ОГРН должен содержать 13 или 15 символов');
            return $msg;
        }

        if ($checkValue == 10)
        {
            $checkValue = 0;
        }

        if ($checkValue == $controlValue)
        {
            return;
        }
        else
        {
            return $msg = Yii::t('validate', "Ошибка. Неверный ОГРН.");
        }
    }
}
