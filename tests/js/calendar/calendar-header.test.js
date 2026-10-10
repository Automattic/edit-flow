import { describe, expect, test } from 'vitest'
import React from 'react'
import { render, fireEvent } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { CalendarHeader } from '../../../modules/calendar/lib/react/calendar-header';

import {
    filters,
    filterValues,
    numberOfWeeks,
    beginningOfWeek,
    pageUrl
} from './setup';

describe("CalendarHeader", () => {

    test("expect you can select a status", async () => {

        const { getByDisplayValue } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        fireEvent.change(getByDisplayValue('Select a status'), {
            target: { value: "publish" }
        });

        expect(getByDisplayValue('Published')).toHaveValue('publish');
    });

    test("expect you can filter a list of users", async () => {
        const { getByPlaceholderText, getByLabelText } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        await userEvent.type(getByPlaceholderText('Select a user'), 'admin');

        expect(getByLabelText('admin')).toHaveTextContent('admin');
    });

    test("expects you can set a user when a user is clicked", async () => {
        const { getByLabelText, getByDisplayValue } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        )

        await userEvent.click(getByLabelText('Open user menu'));
        await userEvent.click(getByLabelText('admin'));

        expect(getByDisplayValue('admin')).toHaveValue('admin');
    });

    test("expects you can close an open user menu on button click", async () => {
        const { getByLabelText, getByDisplayValue } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        await userEvent.click(getByLabelText('Open user menu'));
        await userEvent.click(getByLabelText('Close user menu'));

        expect(getByLabelText('Open user menu')).toBeInTheDocument();
    });

    test("expects you can clear a selected user", async () => {
        const {
            getByLabelText,
            getByPlaceholderText,
            getByDisplayValue
        } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        await userEvent.click(getByLabelText('Open user menu'));
        await userEvent.click(getByLabelText('admin'));

        expect(getByDisplayValue('admin')).toHaveValue('admin');

        await userEvent.click(getByLabelText('Clear user selection'));

        expect(getByPlaceholderText('Select a user')).toHaveValue('');
    });

    test("expect you can filter a list of categories", async () => {
        const { getByPlaceholderText, getByLabelText } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        await userEvent.type(getByPlaceholderText('Select a category'), 'Uncategorized');

        expect(getByLabelText('Uncategorized')).toHaveTextContent('Uncategorized');
    });

    test("expects you can set a category when a category is clicked", async () => {
        const { getByLabelText, getByDisplayValue } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        await userEvent.click(getByLabelText('Open category menu'));
        await userEvent.click(getByLabelText('Uncategorized'));


        expect(getByDisplayValue('Uncategorized')).toHaveValue('Uncategorized');
    });

    test("expects you can close an open category menu on button click", async () => {
        const { getByLabelText } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        await userEvent.click(getByLabelText('Open category menu'));
        await userEvent.click(getByLabelText('Close category menu'));

        expect(getByLabelText('Open category menu')).toBeInTheDocument();
    });

    test("expects you can clear a selected category", async () => {
        const {
            getByLabelText,
            getByPlaceholderText,
            getByDisplayValue
        } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        await userEvent.click(getByLabelText('Open category menu'));
        await userEvent.click(getByLabelText('Uncategorized'));

        expect(getByDisplayValue('Uncategorized')).toHaveValue('Uncategorized');

        await userEvent.click(getByLabelText('Clear category selection'));

        expect(getByPlaceholderText('Select a category')).toHaveValue('');
    });

    test("expect you can select a post type", async () => {
        const { getByDisplayValue } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        fireEvent.change(getByDisplayValue('Select a type'), {
            target: { value: "post" }
        });

        expect(getByDisplayValue('Posts')).toHaveValue('post');
    });

    test("expect you can select a week", async () => {
        const { getByDisplayValue } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        fireEvent.change(getByDisplayValue('6 weeks'), {
            target: { value: "7" }
        });

        expect(getByDisplayValue('7 weeks')).toHaveValue('7');
    });

    test("expects reset button to have empty filter parameters", () => {
        const { getByText } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        expect(getByText('Reset').href).toBe('http://wordpress.test/wp-admin/index.php?page=calendar&post_status=&author=&cat=&cpt=&num_weeks=');
    });

    test("expects that reset button has the correct link", () => {
        const { getByText } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        expect(getByText('Reset').href).toBe('http://wordpress.test/wp-admin/index.php?page=calendar&post_status=&author=&cat=&cpt=&num_weeks=');
    });

    test("expects that today button has the correct link", () => {
        const { getByText } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        expect(getByText('Today').href).toBe('http://wordpress.test/wp-admin/index.php?0=&page=calendar&post_status=&cpt=&cat=0&author=0&num_weeks=6&start_date=2020-04-13');
    });

    test("expects back by total weeks button has the correct link", () => {
        const { getByText } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        expect(getByText('«').href).toBe('http://wordpress.test/wp-admin/index.php?0=&page=calendar&post_status=&cpt=&cat=0&author=0&num_weeks=6&start_date=2020-03-02');
    });

    test("expects back by one week button has the correct link", () => {
        const { getByText } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        expect(getByText('‹').href).toBe('http://wordpress.test/wp-admin/index.php?0=&page=calendar&post_status=&cpt=&cat=0&author=0&num_weeks=6&start_date=2020-04-06');
    });

    test("expects forward by total weeks button has the correct link", () => {
        const { getByText } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        expect(getByText('»').href).toBe('http://wordpress.test/wp-admin/index.php?0=&page=calendar&post_status=&cpt=&cat=0&author=0&num_weeks=6&start_date=2020-05-25');
    });

    test("expects forward by one week button has the correct link", () => {
        const { getByText } = render(
            <CalendarHeader
                filters={filters}
                filterValues={filterValues}
                numberOfWeeks={numberOfWeeks}
                beginningOfWeek={beginningOfWeek}
                pageUrl={pageUrl}
            />
        );

        expect(getByText('›').href).toBe('http://wordpress.test/wp-admin/index.php?0=&page=calendar&post_status=&cpt=&cat=0&author=0&num_weeks=6&start_date=2020-04-20');
    });
});
