<?php

/*
 * The MIT License
 *
 * Copyright (c) 2025 "YooMoney", NBСO LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace YooKassa\Model\Payment\PaymentMethod;

use YooKassa\Model\Payment\PaymentMethodType;
use YooKassa\Validator\Constraints as Assert;

/**
 * Класс, представляющий модель PaymentMethodSbp.
 *
 * Оплата через СБП (Система быстрых платежей ЦБ РФ).
 *
 * @category Class
 * @package  YooKassa\Model
 * @author   cms@yoomoney.ru
 * @link     https://yookassa.ru/developers/api
 *
 * @property string|null $sbp_operation_id Идентификатор операции в СБП (НСПК).
 * @property string|null $sbpOperationId Идентификатор операции в СБП (НСПК).
 * @property SbpPayerBankDetails|null $payer_bank_details Реквизиты счета, который использовался для оплаты.
 * @property SbpPayerBankDetails|null $payerBankDetails Реквизиты счета, который использовался для оплаты.
 */
class PaymentMethodSbp extends AbstractPaymentMethod
{
    /**
     * Идентификатор операции в СБП (НСПК). Пример: `1027088AE4CB48CB81287833347A8777`
     * Обязательный параметр для платежей в статусе ~`succeeded`. В остальных случаях может отсутствовать.
     *
     * @var string|null
     */
    #[Assert\Type('string')]
    private ?string $_sbp_operation_id = null;

    /**
     * Реквизиты счета, который использовался для оплаты.
     * Обязательный параметр для платежей в статусе ~`succeeded`. В остальных случаях может отсутствовать.
     *
     * @var SbpPayerBankDetails|null
     */
    #[Assert\Type(SbpPayerBankDetails::class)]
    private ?SbpPayerBankDetails $_payer_bank_details = null;

    public function __construct(?array $data = [])
    {
        parent::__construct($data);
        $this->setType(PaymentMethodType::SBP);
    }

    /**
     * Возвращает sbp_operation_id.
     *
     * @return string|null Идентификатор операции в СБП (НСПК).
     */
    public function getSbpOperationId(): ?string
    {
        return $this->_sbp_operation_id;
    }

    /**
     * Устанавливает sbp_operation_id.
     *
     * @param string|null $sbp_operation_id Идентификатор операции в СБП (НСПК).
     *
     * @return self
     */
    public function setSbpOperationId(?string $sbp_operation_id = null): self
    {
        $this->_sbp_operation_id = $this->validatePropertyValue('_sbp_operation_id', $sbp_operation_id);
        return $this;
    }

    /**
     * Возвращает payer_bank_details.
     *
     * @return SbpPayerBankDetails|null Реквизиты счета, который использовался для оплаты.
     */
    public function getPayerBankDetails(): ?SbpPayerBankDetails
    {
        return $this->_payer_bank_details;
    }

    /**
     * Устанавливает payer_bank_details.
     *
     * @param SbpPayerBankDetails|array|null $payer_bank_details Реквизиты счета, который использовался для оплаты.
     *
     * @return self
     */
    public function setPayerBankDetails(mixed $payer_bank_details = null): self
    {
        $this->_payer_bank_details = $this->validatePropertyValue('_payer_bank_details', $payer_bank_details);
        return $this;
    }
}
