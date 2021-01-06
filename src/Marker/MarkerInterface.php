<?php


namespace Tev\Marker;

interface MarkerInterface
{
    /**
     * @param $extraInfo : Add any other detail to be logged
     * @return mixed
     */
    public function start($extraInfo);

    /**
     * @param $extraInfo : Add any other detail to be logged
     * @return void
     */
    public function mark($extraInfo);

    /**
     * @param $extraInfo : Add any other detail to be logged
     * @return void
     */
    public function finish($extraInfo);
}
